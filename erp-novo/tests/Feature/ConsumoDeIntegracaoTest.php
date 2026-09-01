<?php

namespace Tests\Feature;

use App\Domain\Integracao\ConsumoDeIntegracao;
use App\Domain\Logistica\Drivers\GoogleRoutesDriver;
use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * F6-01 — quota, custo e finalidade por conta de integração.
 *
 * ## O que já existia
 *
 * Proprietário e credencial (`IntegracaoTenant` resolve empresa → grupo →
 * plataforma, com segredos cifrados) e circuit breaker por credencial.
 *
 * ## O que faltava
 *
 * **Ninguém contava.** Três APIs do Google são cobradas por chamada —
 * geocoding, routes e roads — e o sistema não sabia quantas fazia, por conta de
 * quem, nem quanto custava.
 *
 * Num SaaS isso tem três consequências concretas:
 *
 *  - **a fatura chega sem dono.** Não há como repassar nem saber quem gastou;
 *  - **a quota estoura sem aviso.** O circuit breaker reage *depois* do 403, e
 *    aí o traçado já degradou para linha reta;
 *  - **o fallback silencioso vira dívida.** `googleMapsKey` cai para a chave da
 *    plataforma quando não há grupo resolvido — está logado, mas log não soma.
 *
 * ## Uma linha por dia, não por chamada
 *
 * Geocodificação em lote faz milhares de chamadas. Uma linha cada produziria uma
 * tabela que ninguém consulta e que cresce mais rápido que o dado do negócio. O
 * agregado por (dono, serviço, finalidade, dia) responde o que importa e cabe
 * num índice; o detalhe de uma chamada continua no log.
 */
class ConsumoDeIntegracaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function consumo(): ConsumoDeIntegracao
    {
        return app(ConsumoDeIntegracao::class);
    }

    public function test_a_chamada_fica_registrada_com_dono_e_finalidade(): void
    {
        $empresa = Empresa::factory()->create();

        $this->consumo()->registrar('geocoding', $empresa->id, $empresa->grupo_id, 'geocodificar_cliente');

        $linha = DB::table('integracao_consumos')->first();

        $this->assertSame($empresa->id, (int) $linha->empresa_id);
        $this->assertSame('geocoding', $linha->servico);
        $this->assertSame('geocodificar_cliente', $linha->finalidade);
        $this->assertSame(1, (int) $linha->chamadas);
        $this->assertGreaterThan(0, (int) $linha->custo_centavos, 'custo zero não ajuda ninguém a decidir');
    }

    /**
     * `tenant_account_id` nasce PREENCHIDO.
     *
     * Coluna criada e deixada nula e o defeito que F1 e F4 encontraram duas
     * vezes nesta base — em `estoquehistorico` e nas trilhas de auditoria.
     * Parece resolvida e nao responde pergunta nenhuma; pior que ausente,
     * porque ninguem investiga o que ja parece feito.
     *
     * O guardiao de F1 (`TenantBoundarySchemaTest`) exige a COLUNA; este teste
     * exige o VALOR.
     */
    public function test_o_tenant_da_linha_vem_preenchido(): void
    {
        $empresa = Empresa::factory()->create();
        $tenantId = DB::table('empresas')->where('id', $empresa->id)->value('tenant_account_id');

        $this->consumo()->registrar('geocoding', $empresa->id, $empresa->grupo_id, 'geocodificar_cliente');

        $this->assertSame(
            $tenantId,
            DB::table('integracao_consumos')->value('tenant_account_id'),
            'o tenant vem da empresa; nulo aqui so quando a chave e da plataforma',
        );
    }

    /** Chamadas do mesmo dia somam na mesma linha — não viram milhares de linhas. */
    public function test_chamadas_do_mesmo_dia_somam(): void
    {
        $empresa = Empresa::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->consumo()->registrar('geocoding', $empresa->id, $empresa->grupo_id, 'geocodificar_cliente');
        }

        $this->assertSame(1, DB::table('integracao_consumos')->count());
        $this->assertSame(5, (int) DB::table('integracao_consumos')->value('chamadas'));
    }

    /**
     * Finalidades diferentes somam SEPARADO.
     *
     * `geocodificar_cliente` e `tracar_rota` consomem a mesma chave; quando a
     * fatura sobe, é a finalidade que diz onde olhar. Somar tudo junto
     * devolveria um número verdadeiro e inútil.
     */
    public function test_finalidades_diferentes_nao_se_misturam(): void
    {
        $empresa = Empresa::factory()->create();

        $this->consumo()->registrar('geocoding', $empresa->id, $empresa->grupo_id, 'geocodificar_cliente');
        $this->consumo()->registrar('geocoding', $empresa->id, $empresa->grupo_id, 'importar_logradouros');

        $this->assertSame(2, DB::table('integracao_consumos')->count());
    }

    /** O consumo de uma revenda não entra na conta da outra. */
    public function test_o_consumo_nao_atravessa_empresas(): void
    {
        $a = Empresa::factory()->create();
        $b = Empresa::factory()->create();

        $this->consumo()->registrar('routes', $a->id, $a->grupo_id, 'tracar_rota');
        $this->consumo()->registrar('routes', $a->id, $a->grupo_id, 'tracar_rota');
        $this->consumo()->registrar('routes', $b->id, $b->grupo_id, 'tracar_rota');

        $this->assertSame(2, $this->consumo()->resumo($a->id, '2000-01-01', '2100-01-01')['routes']['chamadas']);
        $this->assertSame(1, $this->consumo()->resumo($b->id, '2000-01-01', '2100-01-01')['routes']['chamadas']);
    }

    /**
     * O fallback para a chave da PLATAFORMA fica visível.
     *
     * Hoje ele só aparece num `Log::warning`, e log não soma. Com dono nulo, o
     * consumo da plataforma vira uma linha somável — que é o que permite
     * descobrir que uma rotina está gastando a cota de todo mundo.
     */
    public function test_consumo_da_plataforma_e_contabilizado_separado(): void
    {
        $empresa = Empresa::factory()->create();

        $this->consumo()->registrar('geocoding', $empresa->id, $empresa->grupo_id, 'geocodificar_cliente');
        $this->consumo()->registrar('geocoding', null, null, 'geocodificar_cliente');

        $daPlataforma = $this->consumo()->resumo(null, '2000-01-01', '2100-01-01');

        $this->assertSame(1, $daPlataforma['geocoding']['chamadas']);
        $this->assertSame(1, $this->consumo()->resumo($empresa->id, '2000-01-01', '2100-01-01')['geocoding']['chamadas']);
    }

    /** Erro conta como chamada (o Google cobra) e marca o health. */
    public function test_erro_conta_como_chamada_e_registra_o_health(): void
    {
        $empresa = Empresa::factory()->create();

        $this->consumo()->registrar(
            'routes', $empresa->id, $empresa->grupo_id, 'tracar_rota',
            erro: true, mensagemErro: 'HTTP 403',
        );

        $linha = DB::table('integracao_consumos')->first();

        $this->assertSame(1, (int) $linha->chamadas, 'a operadora cobra mesmo quando recusa');
        $this->assertSame(1, (int) $linha->erros);
        $this->assertNotNull($linha->ultimo_erro_em, 'health precisa dizer QUANDO parou');
        $this->assertStringContainsString('403', (string) $linha->ultimo_erro);
    }

    /**
     * O registro NUNCA derruba a integração.
     *
     * Instrumentação que interrompe o que ela observa inverte a prioridade — a
     * mesma decisão do `RegistroDaConversao`.
     */
    public function test_falha_ao_registrar_nao_derruba_a_chamada(): void
    {
        Schema::drop('integracao_consumos');

        $this->consumo()->registrar('geocoding', 1, 1, 'geocodificar_cliente');
        $this->assertSame([], $this->consumo()->resumo(1, '2000-01-01', '2100-01-01'));
    }

    /**
     * O driver de rotas registra o consumo de ponta a ponta — com o dono que
     * recebeu na construção.
     */
    public function test_o_driver_de_rotas_registra_o_consumo(): void
    {
        $empresa = Empresa::factory()->create();

        Http::fake(fn () => Http::response(['routes' => [[
            'duration' => '600s', 'distanceMeters' => 5000,
            'polyline' => ['encodedPolyline' => 'abc'],
        ]]], 200));

        (new GoogleRoutesDriver('chave', $empresa->id, $empresa->grupo_id))
            ->tracar(-25.39, -51.45, -25.43, -51.49);

        $linha = DB::table('integracao_consumos')->where('servico', 'routes')->first();

        $this->assertNotNull($linha, 'a chamada cobrada precisa ficar registrada');
        $this->assertSame($empresa->id, (int) $linha->empresa_id);
        $this->assertSame('tracar_rota', $linha->finalidade);
    }

    /** Chamada que falhou também é cobrada, e o driver registra como erro. */
    public function test_o_driver_registra_a_falha_como_erro(): void
    {
        $empresa = Empresa::factory()->create();

        Http::fake(fn () => Http::response(['error' => 'quota'], 403));

        (new GoogleRoutesDriver('chave', $empresa->id, $empresa->grupo_id))
            ->tracar(-25.39, -51.45, -25.43, -51.49);

        $linha = DB::table('integracao_consumos')->where('servico', 'routes')->first();

        $this->assertSame(1, (int) $linha->erros);
        $this->assertStringContainsString('403', (string) $linha->ultimo_erro);
    }
}
