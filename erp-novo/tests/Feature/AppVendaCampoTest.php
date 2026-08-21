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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Telas de venda em campo portadas dos apps legados: busca e cadastro de
 * cliente, vale-gás e relatório.
 *
 * A busca é o que destrava tudo — sem ela a tela de solicitação era
 * inalcançável, porque exige `cliente_id` por parâmetro e nada navegava até lá.
 */
class AppVendaCampoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $vendedor;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->vendedor = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        Colaborador::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'user_id' => $this->vendedor->id, 'vinculo' => 'franqueado',
        ]);
        PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)
            ->create(['grupo_id' => $this->empresa->grupo_id, 'ordem' => 1]);

        $this->token = $this->vendedor->createToken('app', ['role:entregador'])->plainTextToken;
    }

    private function comHeader()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    public function test_busca_cliente_por_nome(): void
    {
        Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'nome' => 'Padaria Central', 'ativo' => true,
        ]);

        $this->comHeader()
            ->getJson('/api/app/v1/entregador/clientes?termo=Padaria')
            ->assertOk()
            ->assertJsonPath('data.0.nome', 'Padaria Central');
    }

    public function test_busca_nao_vaza_cliente_de_outra_empresa(): void
    {
        $outra = Empresa::factory()->create();
        Cliente::factory()->create([
            'empresa_id' => $outra->id, 'grupo_id' => $outra->grupo_id,
            'nome' => 'Alheio', 'ativo' => true,
        ]);

        $r = $this->comHeader()->getJson('/api/app/v1/entregador/clientes?termo=Alheio')->assertOk();

        $this->assertSame([], $r->json('data'));
    }

    public function test_cadastra_cliente_em_campo_sem_missao(): void
    {
        // O `missao/clientes` exige atribuição de missão; o franqueado que passa
        // na porta fora de missão precisa cadastrar do mesmo jeito.
        $r = $this->comHeader()->postJson('/api/app/v1/entregador/clientes', [
            'nome' => 'Mercearia do Ze',
            'cpf' => '123.456.789-00',
            'telefone' => '(42) 99111-2233',
            'endereco' => 'Rua das Flores',
            'numero' => '100',
        ])->assertCreated();

        $this->assertDatabaseHas('clientes', [
            'nome' => 'Mercearia do Ze', 'cpf' => '12345678900',
            'empresa_id' => $this->empresa->id,
        ]);
        $this->assertNotNull($r->json('data.id'));
    }

    public function test_cnpj_de_14_digitos_nao_vira_cpf(): void
    {
        $this->comHeader()->postJson('/api/app/v1/entregador/clientes', [
            'nome' => 'Industria Ltda',
            'cnpj' => '12.345.678/0001-99',
        ])->assertCreated();

        $this->assertDatabaseHas('clientes', ['nome' => 'Industria Ltda', 'cnpj' => '12345678000199']);
    }

    public function test_telefone_duplicado_e_recusado(): void
    {
        $this->comHeader()->postJson('/api/app/v1/entregador/clientes', [
            'nome' => 'Primeiro', 'telefone' => '42999112233',
        ])->assertCreated();

        // Regra herdada do NFWEB: é como a revenda evita dois cadastros para a
        // mesma casa. 422 = DomainException (recusa de regra).
        $this->comHeader()->postJson('/api/app/v1/entregador/clientes', [
            'nome' => 'Segundo', 'telefone' => '(42) 99911-2233',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('clientes', ['nome' => 'Segundo']);
    }

    public function test_vale_gas_inexistente_e_recusado(): void
    {
        $this->comHeader()
            ->postJson('/api/app/v1/entregador/vale-gas/verificar', ['codigo' => 'NAO-EXISTE'])
            ->assertStatus(422);
    }

    public function test_relatorio_de_vendas_soma_o_periodo(): void
    {
        $setor = Setor::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $produto = Produto::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'preco_venda' => 100,
        ]);
        $cliente = Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        app(EstoqueService::class)->entrada($setor->id, $produto->id, 100, 10);

        $concluido = PedidoSituacao::factory()->efeito(EfeitoPedido::CONCLUIDO)
            ->create(['grupo_id' => $this->empresa->grupo_id, 'ordem' => 2]);

        $pedido = app(\App\Domain\Pedido\PedidoService::class)->criar([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $cliente->id,
            'setor_id' => $setor->id,
            'pedidosituacao_id' => $concluido->id,
        ], [['produto_id' => $produto->id, 'quantidade' => 2, 'preco_unitario' => 100]]);

        $pedido->forceFill(['entregador_user_id' => $this->vendedor->id])->save();

        $r = $this->comHeader()->getJson('/api/app/v1/entregador/relatorio-vendas')->assertOk();

        $this->assertSame(1, $r->json('data.quantidade'));
        $this->assertEquals(200, $r->json('data.total'));
    }

    public function test_relatorio_so_traz_venda_concretizada(): void
    {
        // Pedido pendente ainda pode ser cancelado — contar inflaria o número
        // que o vendedor usa para conferir o dia.
        $r = $this->comHeader()->getJson('/api/app/v1/entregador/relatorio-vendas')->assertOk();

        $this->assertSame(0, $r->json('data.quantidade'));
    }
}
