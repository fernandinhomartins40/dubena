<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Pedido\EfeitoPedido;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F0 — ponte de compatibilidade com os apps legados.
 *
 * O contrato do ctrl-web é o oposto do erp-novo: HTTP 200 SEMPRE, com o
 * resultado no corpo (`status` OK/NOK/OPS). Estes testes fixam essa tradução —
 * se ela quebrar, o app em campo passa a ver "erro de conexão" no lugar da
 * mensagem real.
 */
class PonteLegadoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $entregador;

    private Setor $setor;

    private Produto $produto;

    private Cliente $cliente;

    private PedidoSituacao $pendente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->entregador = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $this->setor = Setor::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $this->produto = Produto::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'preco_venda' => 100, 'descricao' => 'Botijão 13kg',
        ]);
        $this->cliente = Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'nome' => 'Dona Maria', 'endereco' => 'Rua das Flores', 'numero' => '100',
        ]);
        $this->pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)
            ->create(['grupo_id' => $this->empresa->grupo_id, 'ordem' => 1]);
        PedidoSituacao::factory()->efeito(EfeitoPedido::CONCLUIDO)
            ->create(['grupo_id' => $this->empresa->grupo_id, 'ordem' => 2]);

        app(EstoqueService::class)->entrada($this->setor->id, $this->produto->id, 100, 10);
    }

    private function comoEntregador(): self
    {
        $this->actingAs($this->entregador, 'sanctum');

        return $this;
    }

    /**
     * Não há PedidoFactory no projeto: pedido nasce pelo PedidoService, que é o
     * caminho real (máquina de estados, totais, fila). Criar por factory pularia
     * tudo isso e testaria um estado que a aplicação nunca produz.
     */
    private function pedidoAtribuido(?int $entregadorId = null): Pedido
    {
        $pedido = app(\App\Domain\Pedido\PedidoService::class)->criar([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->cliente->id,
            'setor_id' => $this->setor->id,
            'pedidosituacao_id' => $this->pendente->id,
            'entrega_urgente' => true,
        ], [[
            'produto_id' => $this->produto->id, 'quantidade' => 2, 'preco_unitario' => 100,
        ]]);

        $pedido->forceFill(['entregador_user_id' => $entregadorId ?? $this->entregador->id])->save();

        return $pedido->refresh();
    }

    public function test_sucesso_devolve_envelope_do_legado_com_dados(): void
    {
        $this->pedidoAtribuido();

        $r = $this->comoEntregador()->postJson('/api/legado/getPedidosPendentes', []);

        $r->assertOk();                       // 200, como o legado
        $r->assertJsonPath('status', 'OK');
        // MovelApp lê `dados`, não `data` (CadastroImportActivity:207).
        $this->assertIsArray($r->json('dados'));
        $this->assertCount(1, $r->json('dados'));
    }

    public function test_campos_do_pedido_no_formato_que_o_app_espera(): void
    {
        $this->pedidoAtribuido();

        $p = $this->comoEntregador()->postJson('/api/legado/getPedidosPendentes', [])->json('dados.0');

        // Nomes e formatos são contrato: o app parseia exatamente isto.
        $this->assertSame('Dona Maria', $p['razao_social']);
        $this->assertSame('Rua das Flores', $p['entregarua']);
        $this->assertSame('100', $p['entreganumero']);
        $this->assertSame('S', $p['urgente']);          // não boolean
        $this->assertSame('-1', (string) $p['motivo_atraso']);
        $this->assertCount(1, $p['itens']);
        $this->assertSame('Botijão 13kg', $p['itens'][0]['produto']);
    }

    public function test_erro_de_validacao_vira_OPS_com_http_200(): void
    {
        // Recusa de REGRA (422 no erp-novo) é o que o legado chama de OPS, e o
        // app trata como resposta válida (Http.js:164). Devolver 422 aqui faria
        // o vendedor ver falha de rede em vez da mensagem.
        $r = $this->comoEntregador()->postJson('/api/legado/setPedidoSituacao', []);

        $r->assertOk();
        $r->assertJsonPath('status', 'OPS');
        $this->assertNotEmpty($r->json('msg'));
    }

    public function test_revenda_divergente_e_barrada_sem_quebrar_o_app(): void
    {
        // O legado obedeceria ao revenda_id (ApiController:34 — IDOR). Aqui é
        // conferido contra o token; a recusa sai como NOK em HTTP 200, para o
        // app mostrar a mensagem em vez de estourar.
        $r = $this->comoEntregador()->postJson('/api/legado/getPedidosPendentes', [
            'revenda_id' => $this->empresa->id + 999,
        ]);

        $r->assertOk();
        $r->assertJsonPath('status', 'NOK');
    }

    public function test_revenda_correta_passa(): void
    {
        $this->pedidoAtribuido();

        $r = $this->comoEntregador()->postJson('/api/legado/getPedidosPendentes', [
            'revenda_id' => $this->empresa->id,
        ]);

        $r->assertJsonPath('status', 'OK');
    }

    public function test_so_ve_os_proprios_pedidos(): void
    {
        $this->pedidoAtribuido();

        // Pedido de outro entregador, mesma empresa.
        $outro = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $this->pedidoAtribuido($outro->id);

        $r = $this->comoEntregador()->postJson('/api/legado/getPedidosPendentes', []);

        $this->assertCount(1, $r->json('dados'));
    }

    public function test_situacoes_traduzem_efeito_em_flags(): void
    {
        $r = $this->comoEntregador()->postJson('/api/legado/getPedidosSituacoes', []);

        $r->assertJsonPath('status', 'OK');
        $flags = collect($r->json('dados'));

        // Os 3 efeitos viram as flags que o app conhece; as 6 sem equivalente
        // ficam zeradas em vez de receberem um mapeamento inventado.
        $this->assertSame(1, $flags->firstWhere('id', $this->pendente->id)['entrega_pendente']);
        $this->assertSame(0, $flags->firstWhere('id', $this->pendente->id)['em_entrega']);
    }

    public function test_baixa_de_pedido_muda_a_situacao(): void
    {
        $pedido = $this->pedidoAtribuido();
        $concluido = PedidoSituacao::query()
            ->where('grupo_id', $this->empresa->grupo_id)
            ->where('efeito', EfeitoPedido::CONCLUIDO->value)->first();

        $r = $this->comoEntregador()->postJson('/api/legado/setPedidoSituacao', [
            'pedido_id' => $pedido->id,
            'pedidosituacao_id' => $concluido->id,
            'pedidomotivoatraso_id' => '-1',
        ]);

        $r->assertJsonPath('status', 'OK');
        $this->assertSame($concluido->id, $pedido->fresh()->pedidosituacao_id);
    }

    public function test_pedido_de_outra_empresa_nao_e_alcancavel(): void
    {
        // Id que não existe nesta empresa (o pedido alheio nem precisa existir:
        // o filtro por empresa_id já o torna inalcançável).
        $r = $this->comoEntregador()->postJson('/api/legado/setPedidoSituacao', [
            'pedido_id' => 999999, 'pedidosituacao_id' => $this->pendente->id,
        ]);

        // 404 → NOK em HTTP 200 (o app mostra a mensagem).
        $r->assertOk();
        $r->assertJsonPath('status', 'NOK');
    }

    public function test_os_11_endpoints_do_movelapp_existem(): void
    {
        // Contrato de COBERTURA: nenhuma funcao do app pode ficar sem destino.
        // Se alguem remover um endpoint achando que "nao e usado", isto cai.
        $esperados = [
            'getEmpresas', 'getPedidosMotivosAtrasos', 'getPedidosPendentes',
            'getPedidosReport', 'getPedidosSituacoes', 'getUsuarios', 'getValeGas',
            'getVeiculos', 'setAndroidMensagem', 'setPedidoSituacao', 'setVeiculoAtivo',
        ];

        $registrados = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
            ->map(fn ($r) => $r->uri())
            ->filter(fn (string $u) => str_starts_with($u, 'api/legado/') && ! str_contains($u, '/nfweb/'))
            ->map(fn (string $u) => str_replace('api/legado/', '', $u))
            ->unique()
            ->values()
            ->all();

        foreach ($esperados as $e) {
            $this->assertContains($e, $registrados, "Endpoint do MovelApp ausente: {$e}");
        }
    }

    public function test_vale_gas_inexistente_recebe_OPS(): void
    {
        $r = $this->comoEntregador()->postJson('/api/legado/getValeGas', ['valegas' => 'NAO-EXISTE'])
            ->assertOk();

        // Recusa de regra, nao erro tecnico: o entregador le a mensagem.
        $r->assertJsonPath('status', 'OPS');
    }

    public function test_report_do_entregador_soma_o_periodo(): void
    {
        $r = $this->comoEntregador()->postJson('/api/legado/getPedidosReport', [])->assertOk();

        $r->assertJsonPath('status', 'OK');
        $this->assertSame(0, $r->json('dados.quantidade'));
    }

    public function test_empresas_devolve_a_revenda_do_token(): void
    {
        $r = $this->comoEntregador()->postJson('/api/legado/getEmpresas', [])->assertOk();

        $r->assertJsonPath('status', 'OK');
        $this->assertSame($this->empresa->id, $r->json('dados.0.id'));
    }
}
