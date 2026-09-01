<?php

namespace Tests\Feature;

use App\Domain\Monitora\Contracts\SgcasaDriver;
use App\Domain\Monitora\Drivers\FakeSgcasaDriver;
use App\Domain\Monitora\Drivers\TraccarDriver;
use App\Domain\Monitora\MonitoraSyncService;
use App\Models\Empresa;
use App\Models\Monitora\UltimaPosicao;
use App\Models\Monitora\Veiculo;
use App\Models\Monitora\VeiculoTipo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Ingestão de posições do Traccar.
 *
 * O rastreamento estava parado no ERP novo: o histórico migrado terminava no dia
 * do dump e nada entrava depois. Os aparelhos GPS funcionavam o tempo todo — o
 * que faltava era o ERP consultar o Traccar, que é o provedor real da frota.
 *
 * O driver não era exercido por nenhum teste ("gate externo"), e foi assim que
 * um driver escrito para uma API que não existe (SGCasa) ficou anos no lugar
 * sem ninguém notar. Aqui a API é simulada com `Http::fake`, que testa a
 * tradução do formato — o que de fato quebra — sem depender do serviço.
 */
class TraccarRastreamentoTest extends TestCase
{
    use RefreshDatabase;

    private function configurarTraccar(): void
    {
        config([
            // Sem escolher o driver, o container resolve o Fake e nada é
            // ingerido — é o mesmo motivo pelo qual o rastreamento ficou parado
            // em produção sem ninguém perceber.
            'services.monitora.driver' => 'traccar',
            'services.traccar.url' => 'http://traccar.teste:8082',
            'services.traccar.usuario' => 'operador@teste',
            'services.traccar.senha' => 'segredo',
            'services.traccar.autocadastrar' => false,
        ]);

        // O binding é singleton: um Fake já resolvido numa asserção anterior
        // continuaria em uso apesar da troca de config.
        $this->app->forgetInstance(SgcasaDriver::class);
    }

    /**
     * Resposta do Traccar no formato real (capturado do servidor em produção).
     *
     * @param  array<string,mixed>  $sobrepor  campos da posição a trocar
     */
    private function fingirTraccar(array $sobrepor = [], string $imei = '467857'): void
    {
        $posicao = array_merge([
            'id' => 29130017,
            'deviceId' => 4,
            'protocol' => 'osmand',
            'fixTime' => now()->subMinute()->toIso8601String(),
            'serverTime' => now()->toIso8601String(),
            'valid' => true,
            'latitude' => -25.394382,
            'longitude' => -51.488901,
            'altitude' => 1045.2,
            // 20 nós = 37,04 km/h. A conversão é o detalhe que mais erra.
            'speed' => 20.0,
            'course' => 90.0,
            'attributes' => ['motion' => true],
        ], $sobrepor);

        Http::fake([
            '*/api/devices' => Http::response([
                ['id' => 4, 'uniqueId' => $imei, 'name' => 'Caminhão Volks', 'disabled' => false],
            ]),
            '*/api/positions' => Http::response([$posicao]),
        ]);
    }

    private function veiculoComImei(string $imei = '467857'): Veiculo
    {
        $empresa = Empresa::factory()->create();

        return Veiculo::create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'placa' => 'BBJ-6878',
            'descricao' => 'Caminhao Volks',
            'imei' => $imei,
            'ativo' => true,
        ]);
    }

    public function test_posicao_do_traccar_vira_ultima_posicao_do_veiculo(): void
    {
        $this->configurarTraccar();
        $this->fingirTraccar();
        $veiculo = $this->veiculoComImei();

        $ingeridas = app(MonitoraSyncService::class)
            ->sincronizar($veiculo->empresa_id);

        $this->assertSame(1, $ingeridas);

        $ultima = UltimaPosicao::where('veiculo_id', $veiculo->id)->first();
        $this->assertNotNull($ultima, 'a posição não chegou à tabela que o mapa lê');
        $this->assertEqualsWithDelta(-25.394382, (float) $ultima->latitude, 0.000001);
        $this->assertEqualsWithDelta(-51.488901, (float) $ultima->longitude, 0.000001);
    }

    /**
     * Nós → km/h.
     *
     * O Traccar reporta velocidade náutica. Sem converter, um caminhão a 37 km/h
     * apareceria como 20 — e nenhum excesso de velocidade seria apontado.
     */
    public function test_velocidade_e_convertida_de_nos_para_kmh(): void
    {
        $this->configurarTraccar();
        $this->fingirTraccar(['speed' => 20.0]);
        $veiculo = $this->veiculoComImei();

        app(MonitoraSyncService::class)->sincronizar($veiculo->empresa_id);

        $this->assertEqualsWithDelta(
            37.04,
            (float) UltimaPosicao::where('veiculo_id', $veiculo->id)->value('velocidade'),
            0.01,
            'velocidade não convertida de nós',
        );
    }

    /** A direção é o que gira o ícone no mapa: sem ela todo veículo aponta ao norte. */
    public function test_direcao_e_ingerida(): void
    {
        $this->configurarTraccar();
        $this->fingirTraccar(['course' => 275.0]);
        $veiculo = $this->veiculoComImei();

        app(MonitoraSyncService::class)->sincronizar($veiculo->empresa_id);

        $this->assertSame(275, (int) UltimaPosicao::where('veiculo_id', $veiculo->id)->value('direcao'));
    }

    /** Ângulo negativo existe em alguns aparelhos e a coluna é unsigned. */
    public function test_direcao_negativa_e_normalizada(): void
    {
        $this->configurarTraccar();
        $this->fingirTraccar(['course' => -90.0]);
        $veiculo = $this->veiculoComImei();

        app(MonitoraSyncService::class)->sincronizar($veiculo->empresa_id);

        $this->assertSame(270, (int) UltimaPosicao::where('veiculo_id', $veiculo->id)->value('direcao'));
    }

    /**
     * Posição sem fix de satélite costuma repetir a última coordenada boa —
     * ingeri-la faz o veículo "teleportar" no mapa.
     */
    public function test_posicao_invalida_e_descartada(): void
    {
        $this->configurarTraccar();
        $this->fingirTraccar(['valid' => false]);
        $veiculo = $this->veiculoComImei();

        $this->assertSame(0, app(MonitoraSyncService::class)->sincronizar($veiculo->empresa_id));
    }

    /**
     * GPS com relógio furado existe: o histórico migrado tem posições em 2080.
     * Gravá-las estragaria qualquer consulta por período.
     */
    public function test_posicao_com_data_no_futuro_e_descartada(): void
    {
        $this->configurarTraccar();
        $this->fingirTraccar(['fixTime' => now()->addYears(50)->toIso8601String()]);
        $veiculo = $this->veiculoComImei();

        $this->assertSame(0, app(MonitoraSyncService::class)->sincronizar($veiculo->empresa_id));
    }

    /** Aparelho que não corresponde a veículo nenhum não pode virar posição órfã. */
    public function test_aparelho_de_outro_imei_nao_gera_posicao(): void
    {
        $this->configurarTraccar();
        config(['services.traccar.autocadastrar' => false]);
        $this->fingirTraccar([], imei: '999999');
        $veiculo = $this->veiculoComImei('467857');

        $this->assertSame(0, app(MonitoraSyncService::class)->sincronizar($veiculo->empresa_id));
    }

    /**
     * F6-02 — o device desconhecido e descartado, mas NAO em silencio.
     *
     * O descarte esta certo: a lista pedida ao provedor sai dos veiculos desta
     * empresa, entao posicao de IMEI que nao consta e dado que nao e dela.
     *
     * O que faltava era o rastro. Um rastreador instalado num caminhao que
     * ninguem cadastrou reporta a noite toda e nao aparece em lugar nenhum — o
     * veiculo fica invisivel no mapa, e ninguem sente falta do que nunca viu.
     *
     * O teste vai ao DRIVER e nao ao servico: e no driver que o filtro por IMEI
     * conhecido acontece, e portanto onde o device desconhecido some.
     */
    public function test_device_desconhecido_fica_registrado(): void
    {
        $this->configurarTraccar();
        $this->fingirTraccar([], imei: '999999');

        $capturado = [];
        Log::listen(function ($evento) use (&$capturado) {
            $capturado[] = $evento;
        });

        // Pede posicoes de um IMEI; o provedor responde com OUTRO.
        $posicoes = app(SgcasaDriver::class)->buscarPosicoes(['467857']);

        $this->assertSame([], $posicoes, 'posicao de device alheio nao entra');

        $aviso = collect($capturado)->first(
            fn ($e) => str_contains($e->message, 'Rastreador reportando'),
        );

        $this->assertNotNull($aviso, 'o descarte precisa deixar rastro');

        // `array_keys` devolve int quando a chave é numérica — o IMEI vira
        // 999999, não '999999'. Comparar como string é o que interessa aqui.
        $this->assertSame(['999999'], array_map('strval', $aviso->context['imeis']));
        $this->assertSame(1, $aviso->context['posicoes_descartadas']);
    }

    /** Rodada normal nao gera aviso — alarme que sempre toca e desligado. */
    public function test_rodada_normal_nao_gera_aviso(): void
    {
        $this->configurarTraccar();
        $this->fingirTraccar();

        $capturado = [];
        Log::listen(function ($evento) use (&$capturado) {
            $capturado[] = $evento;
        });

        app(SgcasaDriver::class)->buscarPosicoes(['467857']);

        $this->assertNull(
            collect($capturado)->first(fn ($e) => str_contains($e->message, 'Rastreador reportando')),
        );
    }

    /**
     * Falha do provedor degrada, não derruba.
     *
     * Sem rastreamento a operação perde visibilidade, mas nada de financeiro ou
     * fiscal fica errado — por isso aqui não vale o fail-closed que o resto do
     * sistema usa para dinheiro.
     */
    public function test_traccar_fora_do_ar_nao_quebra_o_sync(): void
    {
        $this->configurarTraccar();
        Http::fake(['*' => Http::response('', 500)]);
        $veiculo = $this->veiculoComImei();

        $this->assertSame(0, app(MonitoraSyncService::class)->sincronizar($veiculo->empresa_id));
    }

    /** Sem credencial configurada o driver não tenta nada — nem falha. */
    public function test_sem_credencial_nao_consulta(): void
    {
        config(['services.traccar.url' => '', 'services.traccar.usuario' => '']);
        Http::fake();

        $this->assertSame([], app(TraccarDriver::class)->buscarPosicoes(['467857']));
        Http::assertNothingSent();
    }

    /**
     * Aparelho novo instalado num caminhão precisa aparecer.
     *
     * Sem o auto-cadastro ele fica invisível: não está no mapa e ninguém sente
     * falta do que nunca viu.
     */
    public function test_aparelho_sem_veiculo_e_cadastrado_automaticamente(): void
    {
        $this->configurarTraccar();
        config(['services.traccar.autocadastrar' => true]);
        $this->fingirTraccar([], imei: '999888');
        $empresa = Empresa::factory()->create();
        config(['services.traccar.empresa_id' => $empresa->id]);

        app(MonitoraSyncService::class)->sincronizar($empresa->id);

        $novo = Veiculo::where('imei', '999888')->first();
        $this->assertNotNull($novo, 'aparelho novo não virou veículo');
        $this->assertSame('Caminhão Volks', $novo->descricao);
        $this->assertFalse(
            (bool) $novo->ativo,
            'veículo auto-cadastrado precisa nascer inativo — entrar sozinho na operação é decisão de quem opera',
        );
    }

    /** Rodar de novo não pode duplicar o veículo criado na rodada anterior. */
    public function test_autocadastro_nao_duplica_em_rodadas_seguidas(): void
    {
        $this->configurarTraccar();
        config(['services.traccar.autocadastrar' => true]);
        $this->fingirTraccar([], imei: '999888');
        $empresa = Empresa::factory()->create();
        config(['services.traccar.empresa_id' => $empresa->id]);

        $sync = app(MonitoraSyncService::class);
        $sync->sincronizar($empresa->id);
        $sync->sincronizar($empresa->id);

        $this->assertSame(1, Veiculo::where('imei', '999888')->count());
    }

    /**
     * Veículo desativado à mão não pode ser recriado.
     *
     * Quem desativou tinha um motivo; o sync recriando o registro a cada 30 s
     * desfaria a decisão em silêncio.
     */
    public function test_autocadastro_respeita_veiculo_desativado(): void
    {
        $this->configurarTraccar();
        config(['services.traccar.autocadastrar' => true]);
        $this->fingirTraccar([], imei: '777777');
        $empresa = Empresa::factory()->create();
        config(['services.traccar.empresa_id' => $empresa->id]);

        Veiculo::create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'placa' => 'XXX-0000', 'imei' => '777777', 'ativo' => false,
        ]);

        app(MonitoraSyncService::class)->sincronizar($empresa->id);

        $this->assertSame(1, Veiculo::where('imei', '777777')->count());
    }

    /** O driver Fake continua sendo o padrão fora de produção. */
    public function test_driver_padrao_e_o_fake(): void
    {
        config(['services.monitora.driver' => 'fake']);
        $this->assertInstanceOf(FakeSgcasaDriver::class, app(SgcasaDriver::class));
    }

    public function test_driver_traccar_e_escolhido_por_configuracao(): void
    {
        config(['services.monitora.driver' => 'traccar']);
        $this->assertInstanceOf(TraccarDriver::class, app(SgcasaDriver::class));
    }

    /**
     * O mapa precisa de tipo e motorista para desenhar o ícone certo e
     * identificar o veículo — a tela tinha só placa, velocidade e hora.
     */
    public function test_mapa_recebe_tipo_motorista_e_excesso(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        $tipo = VeiculoTipo::create([
            'grupo_id' => $empresa->grupo_id, 'descricao' => 'CAMINHÃO',
            'icone' => 'caminhao', 'velocidade_maxima' => 80, 'ativo' => true,
        ]);
        $veiculo = Veiculo::create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'placa' => 'BBJ-6878', 'descricao' => 'Caminhao Volks',
            'motorista' => 'Sediclei', 'tipo_id' => $tipo->id, 'imei' => '467857', 'ativo' => true,
        ]);
        UltimaPosicao::create([
            'veiculo_id' => $veiculo->id, 'empresa_id' => $empresa->id,
            'latitude' => -25.39, 'longitude' => -51.46,
            'velocidade' => 95, 'direcao' => 180, 'ignicao' => true, 'registrado_em' => now(),
        ]);

        $dado = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/monitora/ultimas-posicoes')
            ->assertOk()
            ->json('data.0');

        $this->assertSame('Sediclei', $dado['motorista']);
        $this->assertSame('CAMINHÃO', $dado['tipo']);
        $this->assertSame('caminhao', $dado['icone']);
        $this->assertSame(180, $dado['direcao']);
        $this->assertSame(80, $dado['velocidade_maxima']);
        $this->assertTrue($dado['excesso'], '95 km/h com máxima de 80 tem de acusar excesso');
    }

    /** Sem tipo cadastrado não há limite — e "sem limite" não é excesso. */
    public function test_veiculo_sem_tipo_nao_acusa_excesso(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        $veiculo = Veiculo::create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'placa' => 'AAA-1111', 'imei' => '111', 'ativo' => true,
        ]);
        UltimaPosicao::create([
            'veiculo_id' => $veiculo->id, 'empresa_id' => $empresa->id,
            'latitude' => -25.39, 'longitude' => -51.46,
            'velocidade' => 200, 'ignicao' => true, 'registrado_em' => now(),
        ]);

        $dado = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/monitora/ultimas-posicoes')
            ->assertOk()
            ->json('data.0');

        $this->assertFalse($dado['excesso']);
        $this->assertNull($dado['velocidade_maxima']);
    }

    /**
     * A aba Rota mostrava "sem trajeto" sem dizer por quê. O período disponível
     * é o que permite distinguir rastreador quebrado de veículo na garagem.
     */
    public function test_periodo_disponivel_informa_o_intervalo_com_historico(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        $veiculo = Veiculo::create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'placa' => 'AAA-2222', 'imei' => '222', 'ativo' => true,
        ]);

        foreach (['2026-08-10 08:00:00', '2026-08-13 16:00:00'] as $quando) {
            $veiculo->posicoes()->create([
                'latitude' => -25.39, 'longitude' => -51.46, 'velocidade' => 10,
                'ignicao' => true, 'registrado_em' => $quando, 'empresa_id' => $empresa->id,
            ]);
        }

        $dado = $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/monitora/veiculos/{$veiculo->id}/periodo")
            ->assertOk()
            ->json('data');

        $this->assertSame('2026-08-10', $dado['inicio']);
        $this->assertSame('2026-08-13', $dado['fim']);
        $this->assertSame(2, $dado['total']);
    }

    public function test_periodo_de_veiculo_sem_historico_vem_nulo(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        $veiculo = Veiculo::create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'placa' => 'AAA-3333', 'imei' => '333', 'ativo' => true,
        ]);

        $dado = $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/monitora/veiculos/{$veiculo->id}/periodo")
            ->assertOk()
            ->json('data');

        $this->assertNull($dado['inicio']);
        $this->assertSame(0, $dado['total']);
    }

    /**
     * O DEFEITO QUE FOI A PRODUCAO.
     *
     * `monitora:sync-positions` percorre todas as empresas ativas, e a lista de
     * aparelhos do Traccar e global. Sem uma empresa dona, cada uma das 12
     * empresas ganhou copia dos mesmos 25 rastreadores: 277 veiculos fantasmas
     * na primeira noite. O teste anterior usava uma empresa so — por isso
     * passava.
     */
    public function test_autocadastro_nao_replica_aparelhos_em_todas_as_empresas(): void
    {
        $this->configurarTraccar();
        config(['services.traccar.autocadastrar' => true]);
        $this->fingirTraccar([], imei: '999888');

        $dona = Empresa::factory()->create();
        $outra = Empresa::factory()->create();
        config(['services.traccar.empresa_id' => $dona->id]);

        $sync = app(MonitoraSyncService::class);
        foreach ([$dona->id, $outra->id] as $empresaId) {
            $sync->sincronizar($empresaId);
        }

        $this->assertSame(
            1,
            Veiculo::where('imei', '999888')->count(),
            'o aparelho foi replicado em outra empresa — a conta no Traccar e uma so',
        );
        $this->assertSame($dona->id, Veiculo::where('imei', '999888')->value('empresa_id'));
    }

    /**
     * Sem dizer de quem sao os rastreadores, o auto-cadastro fica desligado.
     *
     * Adivinhar a empresa e pior que nao cadastrar: enche a frota errada de
     * veiculos que nao existem, e alguem tem de limpar depois.
     */
    public function test_autocadastro_desligado_sem_empresa_dona(): void
    {
        $this->configurarTraccar();
        config(['services.traccar.autocadastrar' => true, 'services.traccar.empresa_id' => null]);
        $this->fingirTraccar([], imei: '999888');
        $empresa = Empresa::factory()->create();

        app(MonitoraSyncService::class)->sincronizar($empresa->id);

        $this->assertSame(0, Veiculo::where('imei', '999888')->count());
    }

    /** Rastreador ja cadastrado em outra empresa pertence a ela. */
    public function test_autocadastro_nao_rouba_aparelho_de_outra_empresa(): void
    {
        $this->configurarTraccar();
        config(['services.traccar.autocadastrar' => true]);
        $this->fingirTraccar([], imei: '555444');

        $outra = Empresa::factory()->create();
        Veiculo::create([
            'empresa_id' => $outra->id, 'grupo_id' => $outra->grupo_id,
            'placa' => 'REA-0001', 'imei' => '555444', 'ativo' => true,
        ]);

        $dona = Empresa::factory()->create();
        config(['services.traccar.empresa_id' => $dona->id]);
        app(MonitoraSyncService::class)->sincronizar($dona->id);

        $this->assertSame(1, Veiculo::where('imei', '555444')->count());
        $this->assertSame($outra->id, Veiculo::where('imei', '555444')->value('empresa_id'));
    }

    /**
     * Aparelho que o ERP nao conhece nao pode ser criado numa empresa que nao e
     * a dona da conta.
     *
     * Este e o teste que isola a PRIMEIRA protecao. O anterior ainda passaria
     * so com a checagem de IMEI duplicado — aqui a empresa errada sincroniza
     * primeiro, entao se o autocadastro nao respeitasse a dona, o veiculo
     * nasceria no lugar errado antes de qualquer duplicata existir.
     */
    public function test_empresa_que_nao_e_dona_nao_cadastra_aparelho(): void
    {
        $this->configurarTraccar();
        config(['services.traccar.autocadastrar' => true]);
        $this->fingirTraccar([], imei: '999888');

        $dona = Empresa::factory()->create();
        $outra = Empresa::factory()->create();
        config(['services.traccar.empresa_id' => $dona->id]);

        // A empresa errada roda PRIMEIRO: sem a trava, e ela quem ficaria com
        // o veiculo, e a dona depois nem o criaria (ja existiria o IMEI).
        app(MonitoraSyncService::class)->sincronizar($outra->id);

        $this->assertSame(
            0,
            Veiculo::where('empresa_id', $outra->id)->where('imei', '999888')->count(),
            'empresa que nao e dona da conta no Traccar cadastrou o aparelho',
        );
    }

    /** O padrao e ficar desligado: ligar e uma decisao consciente. */
    public function test_autocadastro_e_desligado_por_padrao(): void
    {
        $this->assertFalse((bool) config('services.traccar.autocadastrar'));
    }

    /**
     * O provedor devolve a ULTIMA posicao conhecida, tendo ela mudado ou nao.
     *
     * Com polling a cada 30s, um veiculo parado a noite toda regravava a mesma
     * leitura: em producao deram 27.891 linhas num dia para 3.749 posicoes
     * reais, uma repetida 1.859 vezes. No tracado isso empilha pontos no mesmo
     * lugar; no banco, cresce sem trazer informacao nova.
     */
    public function test_mesma_leitura_repetida_nao_e_regravada(): void
    {
        $this->configurarTraccar();
        $this->fingirTraccar();
        $veiculo = $this->veiculoComImei();

        $sync = app(MonitoraSyncService::class);
        $this->assertSame(1, $sync->sincronizar($veiculo->empresa_id));
        // O provedor responde a mesma coisa nas rodadas seguintes.
        $this->assertSame(0, $sync->sincronizar($veiculo->empresa_id));
        $this->assertSame(0, $sync->sincronizar($veiculo->empresa_id));

        $this->assertSame(1, $veiculo->posicoes()->count());
    }
}
