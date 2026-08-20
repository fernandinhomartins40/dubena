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

        // O teto e meta, nao garantia: num percurso reto o RDP corta bem mais
        // que isso, e num sinuoso ele para de afrouxar antes de cortar esquina.
        $this->assertLessThan(900, count($viagem['caminho']), 'a reducao nao agiu');
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

    /**
     * Distancia em metros de um ponto ate o segmento a-b.
     *
     * Repetido aqui de proposito: o teste precisa medir de forma INDEPENDENTE
     * do codigo que verifica, senao um erro na formula passaria despercebido
     * nos dois lados.
     *
     * @param  array{lat:float,lng:float}  $p
     * @param  array{lat:float,lng:float}  $a
     * @param  array{lat:float,lng:float}  $b
     */
    private function metrosAteSegmento(array $p, array $a, array $b): float
    {
        $m = 111320.0;
        $cos = cos($a['lat'] * M_PI / 180);
        $px = ($p['lng'] - $a['lng']) * $m * $cos;
        $py = ($p['lat'] - $a['lat']) * $m;
        $bx = ($b['lng'] - $a['lng']) * $m * $cos;
        $by = ($b['lat'] - $a['lat']) * $m;

        $comprimento = $bx * $bx + $by * $by;
        if ($comprimento < 1e-9) {
            return sqrt($px * $px + $py * $py);
        }

        $t = max(0.0, min(1.0, ($px * $bx + $py * $by) / $comprimento));
        $dx = $px - $t * $bx;
        $dy = $py - $t * $by;

        return sqrt($dx * $dx + $dy * $dy);
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

    /**
     * O DEFEITO DA TELA: o tracado cortava quarteirao.
     *
     * A reducao amostrava de N em N pontos e descartava justamente os vertices
     * das curvas — numa esquina a linha emendava reto por cima do quarteirao,
     * desenhando um triangulo onde o veiculo tinha feito a volta. Este teste
     * fixa que a forma sobrevive: uma conversao em L continua sendo um L.
     */
    public function test_reducao_preserva_a_esquina_e_nao_corta_quarteirao(): void
    {
        [, $veiculo] = $this->cenario();

        // Percurso em L: desce a avenida, vira a esquina, segue pela rua.
        // 900 posicoes forcam a reducao a agir (teto de 400).
        $pontos = [];
        $base = Carbon::parse('2026-08-10 08:00:00');
        $i = 0;
        for ($k = 0; $k < 450; $k++, $i++) {
            $pontos[] = [$base->copy()->addSeconds($i * 20)->format('H:i:s'),
                -25.390 - ($k * 0.00004), -51.4600, 40.0];
        }
        $latEsquina = -25.390 - (449 * 0.00004);
        for ($k = 1; $k <= 450; $k++, $i++) {
            $pontos[] = [$base->copy()->addSeconds($i * 20)->format('H:i:s'),
                $latEsquina, -51.4600 + ($k * 0.00004), 40.0];
        }
        $this->posicoes($veiculo, $pontos);

        $viagem = app(ViagensService::class)
            ->doVeiculo($veiculo, '2026-08-10', '2026-08-10')['viagens'][0];

        $caminho = $viagem['caminho'];
        $this->assertLessThan(900, count($caminho), 'a reducao nao agiu');

        // O vertice da esquina tem de sobreviver: sem ele a linha cortaria em
        // diagonal do meio da avenida ate o meio da rua.
        $achouEsquina = false;
        foreach ($caminho as $p) {
            if (abs($p['lat'] - $latEsquina) < 1e-6 && abs($p['lng'] - (-51.4600)) < 1e-6) {
                $achouEsquina = true;
                break;
            }
        }
        $this->assertTrue($achouEsquina, 'o vertice da esquina foi descartado — a linha corta o quarteirao');
    }

    /**
     * Nenhum ponto do tracado reduzido pode se afastar do percurso real mais
     * que a tolerancia — e o que garante a linha encostada na rua.
     */
    public function test_tracado_reduzido_nao_se_afasta_do_percurso_real(): void
    {
        [, $veiculo] = $this->cenario();

        // Zigue-zague de quarteirao: o caso que mais sofre com amostragem cega.
        $pontos = [];
        $base = Carbon::parse('2026-08-10 08:00:00');
        for ($k = 0; $k < 800; $k++) {
            $volta = intdiv($k, 100) % 2 === 0;
            $pontos[] = [
                $base->copy()->addSeconds($k * 20)->format('H:i:s'),
                -25.390 - ($k * 0.00003),
                -51.460 + ($volta ? ($k % 100) * 0.00002 : (100 - ($k % 100)) * 0.00002),
                40.0,
            ];
        }
        $this->posicoes($veiculo, $pontos);

        $viagem = app(ViagensService::class)
            ->doVeiculo($veiculo, '2026-08-10', '2026-08-10')['viagens'][0];

        // Cada ponto ORIGINAL precisa estar perto da LINHA desenhada — nao de
        // um vertice dela. Medir ate o vertice mais proximo acusaria erro em
        // qualquer trecho reto longo, onde o ponto do meio esta a centenas de
        // metros das pontas e ainda assim exatamente sobre a linha.
        $caminho = $viagem['caminho'];
        $piorDesvio = 0.0;
        foreach ($pontos as $indice => $orig) {
            if ($indice % 25 !== 0) {
                continue;
            }
            $ponto = ['lat' => $orig[1], 'lng' => $orig[2]];
            $menor = PHP_FLOAT_MAX;
            for ($k = 1; $k < count($caminho); $k++) {
                $menor = min($menor, $this->metrosAteSegmento($ponto, $caminho[$k - 1], $caminho[$k]));
            }
            $piorDesvio = max($piorDesvio, $menor);
        }

        $this->assertLessThan(60.0, $piorDesvio,
            "o tracado se afastou {$piorDesvio}m do percurso real — esta cortando caminho");
    }
}
