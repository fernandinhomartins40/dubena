<?php

namespace Tests\Feature;

use App\Domain\Monitora\ViagensService;
use App\Models\Empresa;
use App\Models\Monitora\Veiculo;
use App\Models\Monitora\ViagemCache;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Segmentação do histórico em viagens.
 *
 * A tela de rota desenhava o período inteiro numa linha só. Um dia de entrega
 * tem 300+ posições e passa pelas mesmas ruas várias vezes: o emaranhado não
 * dizia para onde o veículo foi nem quando.
 */
class ViagensRotaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Veiculo} */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => true,
        ]);
        $veiculo = Veiculo::create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'placa' => 'BBJ-6878', 'descricao' => 'Caminhao Volks', 'imei' => '467857', 'ativo' => true,
        ]);

        return [$user, $veiculo];
    }

    /**
     * Grava uma sequência de posições.
     *
     * @param  list<array{0:string,1:float,2:float,3:float}>  $pontos  [hora, lat, lng, velocidade]
     */
    private function posicoes(Veiculo $veiculo, array $pontos, string $data = '2026-08-10'): void
    {
        foreach ($pontos as [$hora, $lat, $lng, $vel]) {
            $veiculo->posicoes()->create([
                'empresa_id' => $veiculo->empresa_id,
                'latitude' => $lat, 'longitude' => $lng,
                'velocidade' => $vel, 'ignicao' => $vel > 0,
                'registrado_em' => "{$data} {$hora}",
            ]);
        }
    }

    /**
     * Trajeto de ~1,5 km em linha, andando.
     *
     * @return list<array{0:string,1:float,2:float,3:float}>
     */
    private function trecho(string $horaInicial, int $minutos = 0): array
    {
        $base = Carbon::parse("2026-08-10 {$horaInicial}")->addMinutes($minutos);
        $saida = [];
        for ($i = 0; $i < 6; $i++) {
            $saida[] = [
                $base->copy()->addMinutes($i * 2)->format('H:i:s'),
                -25.390 - ($i * 0.003),
                -51.460,
                40.0,
            ];
        }

        return $saida;
    }

    public function test_um_trecho_continuo_vira_uma_viagem(): void
    {
        [, $veiculo] = $this->cenario();
        $this->posicoes($veiculo, $this->trecho('08:00:00'));

        $saida = app(ViagensService::class)->doVeiculo($veiculo, '2026-08-10', '2026-08-10');

        $this->assertCount(1, $saida['viagens']);
        $this->assertSame('08:00', Carbon::parse($saida['viagens'][0]['inicio'])->format('H:i'));
        $this->assertSame('08:10', Carbon::parse($saida['viagens'][0]['fim'])->format('H:i'));
    }

    /**
     * O corte é o que separa uma entrega da outra.
     *
     * Sem ele o dia inteiro vira uma linha só, que é o defeito relatado.
     */
    public function test_parada_longa_separa_duas_viagens(): void
    {
        [, $veiculo] = $this->cenario();

        $this->posicoes($veiculo, [
            ...$this->trecho('08:00:00'),
            // 20 min parado no mesmo ponto: a entrega.
            ['08:12:00', -25.405, -51.460, 0.0],
            ['08:32:00', -25.405, -51.460, 0.0],
            ...$this->trecho('08:35:00'),
        ]);

        $saida = app(ViagensService::class)->doVeiculo($veiculo, '2026-08-10', '2026-08-10');

        $this->assertCount(2, $saida['viagens'], 'a parada de 20 min deveria ter separado as viagens');
    }

    /** Parada curta (semáforo, portão) não pode picar a viagem em pedaços. */
    public function test_parada_curta_nao_separa(): void
    {
        [, $veiculo] = $this->cenario();

        $this->posicoes($veiculo, [
            ...$this->trecho('08:00:00'),
            ['08:11:00', -25.405, -51.460, 0.0],
            ['08:13:00', -25.405, -51.460, 0.0],
            ...$this->trecho('08:15:00'),
        ]);

        $saida = app(ViagensService::class)->doVeiculo($veiculo, '2026-08-10', '2026-08-10');

        $this->assertCount(1, $saida['viagens']);
    }

    /**
     * Rastreador em veículo estacionado oscila alguns metros. Sem o piso de
     * distância, isso viraria dezenas de "viagens" de 20 segundos.
     */
    public function test_oscilacao_de_gps_parado_nao_vira_viagem(): void
    {
        [, $veiculo] = $this->cenario();

        $this->posicoes($veiculo, [
            ['08:00:00', -25.39000, -51.46000, 0.0],
            ['08:00:30', -25.39002, -51.46001, 0.0],
            ['08:01:00', -25.39001, -51.46002, 0.0],
            ['08:01:30', -25.39000, -51.46000, 0.0],
        ]);

        $saida = app(ViagensService::class)->doVeiculo($veiculo, '2026-08-10', '2026-08-10');

        $this->assertCount(0, $saida['viagens']);
    }

    /**
     * Buraco no tempo (rastreador desligado, área sem sinal) também corta.
     *
     * Emendar os dois lados desenharia uma reta atravessando a cidade por onde
     * o veículo nunca passou.
     */
    public function test_buraco_no_sinal_separa_viagens(): void
    {
        [, $veiculo] = $this->cenario();

        $this->posicoes($veiculo, [
            ...$this->trecho('08:00:00'),
            ['08:11:00', -25.405, -51.460, 0.0],
            // Duas horas sem reportar.
            ...$this->trecho('10:15:00'),
        ]);

        $saida = app(ViagensService::class)->doVeiculo($veiculo, '2026-08-10', '2026-08-10');

        $this->assertCount(2, $saida['viagens']);
    }

    public function test_resumo_soma_distancia_e_duracao(): void
    {
        [, $veiculo] = $this->cenario();
        $this->posicoes($veiculo, $this->trecho('08:00:00'));

        $saida = app(ViagensService::class)->doVeiculo($veiculo, '2026-08-10', '2026-08-10');

        $this->assertSame(1, $saida['resumo']['total']);
        // ~1,67 km: 5 passos de 0,003° de latitude.
        $this->assertGreaterThan(1.0, $saida['resumo']['distancia_km']);
        $this->assertLessThan(2.5, $saida['resumo']['distancia_km']);
        $this->assertGreaterThan(0, $saida['resumo']['duracao_min']);
    }

    /**
     * O caminho é reduzido para não trafegar milhares de coordenadas — mas
     * origem e destino não podem se deslocar.
     */
    public function test_caminho_reduzido_preserva_origem_e_destino(): void
    {
        [, $veiculo] = $this->cenario();

        // 900 posições: acima do teto de 400 do redutor.
        $pontos = [];
        $base = Carbon::parse('2026-08-10 08:00:00');
        for ($i = 0; $i < 900; $i++) {
            $pontos[] = [
                $base->copy()->addSeconds($i * 20)->format('H:i:s'),
                -25.390 - ($i * 0.00002),
                -51.460,
                40.0,
            ];
        }
        $this->posicoes($veiculo, $pontos);

        $viagem = app(ViagensService::class)
            ->doVeiculo($veiculo, '2026-08-10', '2026-08-10')['viagens'][0];

        $this->assertLessThanOrEqual(401, count($viagem['caminho']));
        $this->assertSame(900, $viagem['pontos'], 'a contagem original deve ser preservada');

        $primeiro = $viagem['caminho'][0];
        $ultimo = $viagem['caminho'][count($viagem['caminho']) - 1];
        $this->assertEqualsWithDelta($viagem['origem']['lat'], $primeiro['lat'], 0.0000001);
        $this->assertEqualsWithDelta($viagem['destino']['lat'], $ultimo['lat'], 0.0000001);
    }

    /**
     * Período encerrado é cacheado: a tela é reaberta o tempo todo e varrer as
     * posições do dia a cada visita é desperdício.
     */
    public function test_periodo_encerrado_e_gravado_no_cache(): void
    {
        [, $veiculo] = $this->cenario();
        $this->posicoes($veiculo, $this->trecho('08:00:00'));

        app(ViagensService::class)->doVeiculo($veiculo, '2026-08-10', '2026-08-10');

        $registro = ViagemCache::where('veiculo_id', $veiculo->id)->first();
        $this->assertNotNull($registro, 'periodo encerrado deveria ter sido cacheado');
        $this->assertSame('2026-08-10', $registro->de->toDateString());
        $this->assertSame('2026-08-10', $registro->ate->toDateString());
    }

    public function test_segunda_consulta_usa_o_cache(): void
    {
        [, $veiculo] = $this->cenario();
        $this->posicoes($veiculo, $this->trecho('08:00:00'));

        $servico = app(ViagensService::class);
        $servico->doVeiculo($veiculo, '2026-08-10', '2026-08-10');
        $servico->doVeiculo($veiculo, '2026-08-10', '2026-08-10');

        $this->assertSame(
            1,
            (int) ViagemCache::where('veiculo_id', $veiculo->id)->value('hits'),
            'a segunda consulta deveria ter contado um hit de cache',
        );
    }

    /**
     * Hoje NÃO entra no cache: o veículo ainda está rodando, e servir o
     * resultado congelado esconderia o resto do dia.
     */
    public function test_periodo_que_inclui_hoje_nao_e_cacheado(): void
    {
        [, $veiculo] = $this->cenario();
        $hoje = now()->toDateString();

        $this->posicoes($veiculo, $this->trecho('08:00:00'), $hoje);

        app(ViagensService::class)->doVeiculo($veiculo, $hoje, $hoje);

        $this->assertSame(
            0,
            ViagemCache::where('veiculo_id', $veiculo->id)->count(),
            'periodo que inclui hoje nao pode ser cacheado — o veiculo ainda esta rodando',
        );
    }

    public function test_endpoint_devolve_as_viagens(): void
    {
        [$user, $veiculo] = $this->cenario();
        $this->posicoes($veiculo, $this->trecho('08:00:00'));

        $dado = $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/monitora/veiculos/{$veiculo->id}/viagens?de=2026-08-10&ate=2026-08-10")
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $dado['viagens']);
        $this->assertArrayHasKey('caminho', $dado['viagens'][0]);
        $this->assertArrayHasKey('resumo', $dado);
    }

    public function test_endpoint_exige_periodo_coerente(): void
    {
        [$user, $veiculo] = $this->cenario();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/monitora/veiculos/{$veiculo->id}/viagens?de=2026-08-15&ate=2026-08-10")
            ->assertStatus(422);
    }

    public function test_veiculo_de_outra_empresa_nao_e_acessivel(): void
    {
        [$user] = $this->cenario();
        [, $outro] = $this->cenario();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/monitora/veiculos/{$outro->id}/viagens?de=2026-08-10&ate=2026-08-10")
            ->assertNotFound();
    }
}
