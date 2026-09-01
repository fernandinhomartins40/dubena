<?php

namespace Tests\Feature;

use App\Domain\Logistica\CentralService;
use App\Domain\Logistica\DistribuidorService;
use App\Domain\Logistica\Jobs\AtribuirPedidoJob;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Logistica\Jornada;
use App\Models\Logistica\LogisticaConfig;
use App\Models\Logistica\PedidoAtribuicao;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F6-06 — a atribuição automática guarda a REGRA que decidiu.
 *
 * ## O que já estava certo
 *
 * `pedido_atribuicoes` é uma boa trilha: de quem, para quem, veículo, operador,
 * ação, se foi automático e o motivo. A autoria **humana** estava resolvida.
 *
 * ## O que faltava
 *
 * No caminho automático o motivo era uma string fixa —
 * `'Auto-atribuição (distância/carga)'`. Ela diz *que critério* decidiu e não
 * *com quais valores*. E os valores importam: `peso_distancia`, `peso_carga`,
 * raio máximo e teto de carga são **configuráveis por empresa** e mudam.
 *
 * Então quando o operador contesta — *"por que foi para aquele entregador, se
 * tinha outro mais perto?"* — a resposta era irreproduzível. Rodar o ranking de
 * novo usa os pesos de **hoje**, e a conclusão sai errada nas duas direções:
 * culpando o algoritmo por uma decisão correta, ou inocentando-o de uma errada.
 *
 * ## Congela, não referencia
 *
 * Mesma razão do snapshot fiscal (F5-08): a config é editável, e uma trilha que
 * aponta para dado mutável não é trilha.
 */
class AutoriaDaAtribuicaoTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Empresa, Pedido, User} */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $setor = Setor::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'preco_venda' => 100,
        ]);
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'latitude' => -25.3935, 'longitude' => -51.4562,
        ]);
        $situacao = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)
            ->create(['grupo_id' => $empresa->grupo_id]);

        $pedido = app(PedidoService::class)->criar([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $situacao->id,
            'setor_id' => $setor->id,
        ], [['produto_id' => $produto->id, 'quantidade' => 1]]);

        // Entregador em jornada ativa — o candidato do ranking.
        $entregador = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        Jornada::query()->create([
            'empresa_id' => $empresa->id,
            'entregador_user_id' => $entregador->id,
            'status' => 'ativa',
            'iniciada_em' => now(),
        ]);

        return [$empresa, $pedido, $entregador];
    }

    /** Config em modo AUTO — sem ela o job sai antes de ranquear. */
    private function configAuto(Empresa $empresa, float $pesoDistancia = 0.6, float $pesoCarga = 0.4): LogisticaConfig
    {
        return LogisticaConfig::query()->create([
            'empresa_id' => $empresa->id,
            'modo' => LogisticaConfig::MODO_AUTO,
            'peso_distancia' => $pesoDistancia,
            'peso_carga' => $pesoCarga,
        ]);
    }

    private function trilhaDe(Pedido $pedido): ?PedidoAtribuicao
    {
        return PedidoAtribuicao::query()->where('pedido_id', $pedido->id)->latest('id')->first();
    }

    /**
     * O caso da tarefa: a atribuição automática registra a regra e os pesos.
     */
    public function test_atribuicao_automatica_congela_a_regra_e_os_parametros(): void
    {
        [$empresa, $pedido] = $this->cenario();

        // O job so atua em modo AUTO — em modo sugerir a decisao e do operador.
        $this->configAuto($empresa);

        // `dispatchSync` deixa o container injetar as dependencias do `handle` —
        // a assinatura dele tem quatro parametros e uma delas e opcional.
        AtribuirPedidoJob::dispatchSync($pedido->id, $empresa->id, $empresa->grupo_id);

        $trilha = $this->trilhaDe($pedido);

        $this->assertNotNull($trilha, 'a atribuição precisa ter acontecido');
        $this->assertTrue((bool) $trilha->automatico);
        $this->assertSame(DistribuidorService::REGRA, $trilha->regra, 'qual regra decidiu');

        $this->assertIsArray($trilha->regra_parametros);
        $this->assertArrayHasKey('peso_distancia', $trilha->regra_parametros);
        $this->assertArrayHasKey('peso_carga', $trilha->regra_parametros);
        $this->assertNotNull($trilha->score, 'e quão bom era o escolhido');
    }

    /**
     * Os parâmetros congelados são os DA EMPRESA, não os defaults.
     *
     * É o teste que dá sentido ao congelamento: se gravasse sempre o default, a
     * trilha mentiria justamente para quem configurou algo diferente.
     */
    public function test_os_parametros_congelados_sao_os_da_empresa(): void
    {
        [$empresa, $pedido] = $this->cenario();

        $this->configAuto($empresa, pesoDistancia: 0.9, pesoCarga: 0.1);

        // `dispatchSync` deixa o container injetar as dependencias do `handle` —
        // a assinatura dele tem quatro parametros e uma delas e opcional.
        AtribuirPedidoJob::dispatchSync($pedido->id, $empresa->id, $empresa->grupo_id);

        $parametros = $this->trilhaDe($pedido)->regra_parametros;

        $this->assertSame(0.9, (float) $parametros['peso_distancia']);
        $this->assertSame(0.1, (float) $parametros['peso_carga']);
    }

    /**
     * Mudar a config DEPOIS não reescreve a trilha.
     *
     * É a propriedade que torna a decisão reproduzível anos depois — e a razão
     * de congelar em vez de referenciar a config.
     */
    public function test_mudar_a_config_depois_nao_reescreve_a_trilha(): void
    {
        [$empresa, $pedido] = $this->cenario();

        $config = $this->configAuto($empresa, pesoDistancia: 0.9, pesoCarga: 0.1);

        // `dispatchSync` deixa o container injetar as dependencias do `handle` —
        // a assinatura dele tem quatro parametros e uma delas e opcional.
        AtribuirPedidoJob::dispatchSync($pedido->id, $empresa->id, $empresa->grupo_id);

        // O gestor reequilibra os pesos no mês seguinte.
        $config->update(['peso_distancia' => 0.2, 'peso_carga' => 0.8]);

        $this->assertSame(
            0.9,
            (float) $this->trilhaDe($pedido)->refresh()->regra_parametros['peso_distancia'],
            'a trilha guarda o que valia NA HORA da decisão',
        );
    }

    /**
     * Atribuição MANUAL não inventa regra.
     *
     * Quem decidiu foi uma pessoa, e o campo `operador_user_id` já responde por
     * isso. Preencher `regra` aqui daria a entender que um algoritmo participou.
     */
    public function test_atribuicao_manual_nao_tem_regra(): void
    {
        [$empresa, $pedido, $entregador] = $this->cenario();
        $operador = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        app(CentralService::class)->atribuir(
            $pedido, $empresa->id, $entregador->id, null, $operador->id, false, 'Cliente pediu este entregador',
        );

        $trilha = $this->trilhaDe($pedido);

        $this->assertFalse((bool) $trilha->automatico);
        $this->assertNull($trilha->regra, 'ninguém além da pessoa decidiu');
        $this->assertNull($trilha->regra_parametros);
        $this->assertSame($operador->id, (int) $trilha->operador_user_id, 'e a autoria humana continua registrada');
    }

    /**
     * Guardião: a auto-atribuição não volta a gravar só o rótulo.
     *
     * O sinal é `atribuir(...)` com `automatico: true` sem a decisão junto —
     * que é exatamente como o código estava, e a forma mais provável de a
     * próxima regra ser escrita.
     */
    public function test_a_auto_atribuicao_sempre_passa_a_decisao(): void
    {
        $fonte = (string) file_get_contents(
            app_path('Domain/Logistica/Jobs/AtribuirPedidoJob.php'),
        );

        $this->assertStringContainsString(
            'DistribuidorService::REGRA',
            $fonte,
            'a auto-atribuição precisa nomear a regra que usou',
        );
        $this->assertStringContainsString(
            'parametrosDaRegra',
            $fonte,
            'e congelar os parâmetros — o rótulo sozinho não reproduz a decisão',
        );
    }
}
