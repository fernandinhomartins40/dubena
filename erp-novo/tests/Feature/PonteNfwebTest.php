<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Pedido\EfeitoPedido;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\Rh\Colaborador;
use App\Models\User;
use App\Models\Venda\PedidoSolicitacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F0 — ponte do NFWEB, o app legado que está mais vivo (build de 17/07/2025 e o
 * único que realmente imprime).
 *
 * Dois contratos a preservar e uma regra a MUDAR:
 *  - envelope `data` (o NFWEB lê `responseHttp.data`, Http.js:164) e HTTP 200
 *    sempre;
 *  - `savePedido` passa a criar SOLICITAÇÃO, não pedido: a regra do cliente é
 *    que o vendedor pede e a Central decide.
 */
class PonteNfwebTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $vendedor;

    private Cliente $cliente;

    private Produto $produto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->vendedor = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        Colaborador::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'user_id' => $this->vendedor->id, 'vinculo' => 'industrial',
            'nome' => 'Carlos Industrial', 'telefone' => '42999887766', 'ativo' => true,
        ]);
        $setor = Setor::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $this->produto = Produto::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'descricao' => 'Botijao 13kg', 'preco_venda' => 100,
            'ativo' => true, 'envia_app_nf' => true,
        ]);
        $this->cliente = Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'nome' => 'Padaria Central', 'ativo' => true,
        ]);
        PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)
            ->create(['grupo_id' => $this->empresa->grupo_id, 'ordem' => 1]);

        app(EstoqueService::class)->entrada($setor->id, $this->produto->id, 100, 10);

        $this->actingAs($this->vendedor, 'sanctum');
    }

    public function test_init_devolve_carga_no_envelope_data(): void
    {
        $r = $this->postJson('/api/legado/nfweb/init', [])->assertOk();

        // NFWEB lê `data`, não `dados` (Http.js:164) — o MovelApp é o contrário.
        $r->assertJsonPath('status', 'OK');
        $this->assertSame('Carlos Industrial', $r->json('data.colaborador.nome'));

        // O app lê `precovenda` (nome do legado), não `preco_venda`.
        // JSON serializa 100.0 como 100 — comparar o valor, nao o tipo.
        $this->assertEquals(100, $r->json('data.produtos.0.precovenda'));
    }

    public function test_init_so_traz_produto_marcado_para_o_app(): void
    {
        Produto::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'descricao' => 'Nao aparece', 'ativo' => true, 'envia_app_nf' => false,
        ]);

        $produtos = $this->postJson('/api/legado/nfweb/init', [])->json('data.produtos');

        // `envia_app_nf` veio migrado do legado e é o que decide o catálogo deste
        // app — respeitá-la mantém a tela igual à de hoje.
        $this->assertCount(1, $produtos);
    }

    public function test_colaborador_inativo_recebe_OPS_e_nao_erro_de_rede(): void
    {
        Colaborador::query()->where('user_id', $this->vendedor->id)->update(['ativo' => false]);

        $r = $this->postJson('/api/legado/nfweb/init', [])->assertOk();

        // 422 → OPS: recusa de regra, que o app trata como resposta válida e
        // mostra ao vendedor. Devolver 4xx faria virar "erro de conexão".
        $r->assertJsonPath('status', 'OPS');
    }

    public function test_savePedido_cria_solicitacao_e_nao_pedido(): void
    {
        $r = $this->postJson('/api/legado/nfweb/savePedido', [
            'pedido' => [
                'cliente' => ['id' => $this->cliente->id],
                'produtos' => [['id' => $this->produto->id, 'qtde' => 2]],
                'desconto' => '15,00',
                'observacoes' => 'Entregar pela manhã',
            ],
        ])->assertOk();

        $r->assertJsonPath('status', 'OK');

        // A mudança de comportamento: o legado fechava o pedido na hora, com
        // preço livre. Agora nasce solicitação — e nenhum pedido.
        $this->assertDatabaseCount('pedido_solicitacoes', 1);
        $this->assertDatabaseCount('pedidos', 0);

        $s = PedidoSolicitacao::first();
        $this->assertSame('15.00', $s->desconto_solicitado);
        // Preço do CADASTRO, não do app.
        $this->assertSame(100.0, (float) $s->itens[0]['preco_unitario']);
    }

    public function test_savePedido_sem_produto_recebe_OPS(): void
    {
        $r = $this->postJson('/api/legado/nfweb/savePedido', [
            'pedido' => ['cliente' => ['id' => $this->cliente->id], 'produtos' => []],
        ])->assertOk();

        $r->assertJsonPath('status', 'OPS');
        $this->assertDatabaseCount('pedido_solicitacoes', 0);
    }

    public function test_getCliente_busca_por_nome(): void
    {
        $r = $this->postJson('/api/legado/nfweb/getCliente', ['termo' => 'Padaria'])->assertOk();

        $r->assertJsonPath('status', 'OK');
        $this->assertSame('Padaria Central', $r->json('data.0.nome'));
    }

    public function test_cliente_de_outra_empresa_nao_aparece(): void
    {
        $outra = Empresa::factory()->create();
        Cliente::factory()->create([
            'empresa_id' => $outra->id, 'grupo_id' => $outra->grupo_id,
            'nome' => 'Padaria Alheia', 'ativo' => true,
        ]);

        $nomes = collect($this->postJson('/api/legado/nfweb/getCliente', ['termo' => 'Padaria'])->json('data'))
            ->pluck('nome');

        $this->assertContains('Padaria Central', $nomes);
        $this->assertNotContains('Padaria Alheia', $nomes);
    }

    public function test_parcelas_vencidas_sem_cliente_recebe_OPS(): void
    {
        $this->postJson('/api/legado/nfweb/getParcelasVencidasCliente', [])
            ->assertOk()
            ->assertJsonPath('status', 'OPS');
    }

    public function test_revenda_divergente_e_barrada(): void
    {
        $this->postJson('/api/legado/nfweb/init', ['revenda_id' => $this->empresa->id + 999])
            ->assertOk()
            ->assertJsonPath('status', 'NOK');
    }

    public function test_as_18_rotas_do_legado_existem(): void
    {
        // Contrato de COBERTURA: o app tem de continuar inteiro depois de
        // apontar para a ponte. Se alguem remover uma rota achando que "nao e
        // usada", este teste cai.
        $esperadas = [
            'login', 'init', 'changeRegistrationId', 'changeVeiculo',
            'getParcelasVencidasCliente', 'getCliente', 'savePedido', 'saveCliente',
            'saveClienteObs', 'pedidosReport', 'pedidoConsulta', 'nfeConsulta',
            'pedidoDuplicata', 'visualizarDanfe', 'enviarEmail', 'visualizarBoleto',
            'getCadastros', 'baixarDanfe',
        ];

        $registradas = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
            ->map(fn ($r) => $r->uri())
            ->filter(fn (string $u) => str_starts_with($u, 'api/legado/nfweb/'))
            ->map(fn (string $u) => str_replace('api/legado/nfweb/', '', $u))
            ->unique()
            ->values();

        foreach ($esperadas as $rota) {
            $this->assertContains($rota, $registradas->all(), "Rota do legado ausente: {$rota}");
        }
    }

    public function test_saveCliente_cadastra_em_campo(): void
    {
        $r = $this->postJson('/api/legado/nfweb/saveCliente', [
            'cliente' => [
                'nome' => 'Mercearia do Ze',
                'tipopessoa' => ['id' => 1, 'tipopessoacadastro' => 'F'],
                'cpf' => '123.456.789-00',
                'telefones' => [['telefone' => '(42) 99111-2233']],
            ],
        ])->assertOk();

        $r->assertJsonPath('status', 'OK');
        $this->assertDatabaseHas('clientes', ['nome' => 'Mercearia do Ze', 'cpf' => '12345678900']);
    }

    public function test_saveCliente_recusa_telefone_ja_usado(): void
    {
        // Regra do legado: telefone duplicado rejeita — e como a revenda evita
        // dois cadastros para a mesma casa.
        $this->postJson('/api/legado/nfweb/saveCliente', [
            'cliente' => [
                'nome' => 'Primeiro',
                'tipopessoa' => ['tipopessoacadastro' => 'F'],
                'telefones' => [['telefone' => '42999112233']],
            ],
        ])->assertOk();

        $r = $this->postJson('/api/legado/nfweb/saveCliente', [
            'cliente' => [
                'nome' => 'Segundo',
                'tipopessoa' => ['tipopessoacadastro' => 'F'],
                'telefones' => [['telefone' => '(42) 99911-2233']],
            ],
        ])->assertOk();

        $r->assertJsonPath('status', 'OPS');
        $this->assertDatabaseMissing('clientes', ['nome' => 'Segundo']);
    }

    public function test_pedidosReport_soma_o_periodo(): void
    {
        $r = $this->getJson('/api/legado/nfweb/pedidosReport')->assertOk();

        $r->assertJsonPath('status', 'OK');
        $this->assertSame(0, $r->json('data.quantidade'));
    }

    public function test_getCadastros_traz_as_listas_do_formulario(): void
    {
        $r = $this->getJson('/api/legado/nfweb/getCadastros')->assertOk();

        $r->assertJsonPath('status', 'OK');
        $this->assertIsArray($r->json('data.tipospessoa'));
        $this->assertCount(2, $r->json('data.tipospessoa'));
    }

    public function test_saveClienteObs_grava_a_anotacao(): void
    {
        $r = $this->postJson('/api/legado/nfweb/saveClienteObs', [
            'cliente_id' => $this->cliente->id,
            'observacoes' => 'Portao azul, cachorro bravo',
        ])->assertOk();

        $r->assertJsonPath('status', 'OK');
        $this->assertSame('Portao azul, cachorro bravo', $this->cliente->fresh()->observacoes);
    }
}
