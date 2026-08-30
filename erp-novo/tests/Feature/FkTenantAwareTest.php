<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Pedido\EfeitoPedido;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F2-02 — validação de FK respeita a fronteira do tenant.
 *
 * `exists:clientes,id` valida contra a tabela INTEIRA. Medido antes da correção:
 * um POST /pedidos da empresa A informando `cliente_id` da empresa B era aceito
 * com HTTP 201 — e continuava aceito entre TENANTS distintos, mesmo com o
 * enforcement ligado.
 *
 * A RLS protege a leitura; a validação corria por fora, e a FK nascia cruzada.
 */
class FkTenantAwareTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Empresa, Setor, Produto, PedidoSituacao} */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $setor = Setor::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'preco_venda' => 100,
        ]);
        app(EstoqueService::class)->entrada($setor->id, $produto->id, 100, 10);

        $situacao = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)
            ->create(['grupo_id' => $empresa->grupo_id]);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        return [$user, $empresa, $setor, $produto, $situacao];
    }

    public function test_pedido_recusa_cliente_de_outra_empresa(): void
    {
        [$user, $empresa, $setor, $produto, $situacao] = $this->cenario();

        // Mesma rede, outra unidade: o `exists` nativo aceitava.
        $outra = Empresa::factory()->create(['grupo_id' => $empresa->grupo_id]);
        $clienteAlheio = Cliente::factory()->create([
            'empresa_id' => $outra->id, 'grupo_id' => $outra->grupo_id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/pedidos', [
                'cliente_id' => $clienteAlheio->id,
                'pedidosituacao_id' => $situacao->id,
                'setor_id' => $setor->id,
                'itens' => [['produto_id' => $produto->id, 'quantidade' => 1]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cliente_id');
    }

    public function test_pedido_recusa_cliente_de_outro_tenant(): void
    {
        config()->set('saas_transformation.enforcement.tenant_envelope', true);
        [$user, , $setor, $produto, $situacao] = $this->cenario();

        // Grupo diferente = tenant diferente: revenda concorrente.
        $concorrente = Empresa::factory()->create();
        $clienteAlheio = Cliente::factory()->create([
            'empresa_id' => $concorrente->id, 'grupo_id' => $concorrente->grupo_id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/pedidos', [
                'cliente_id' => $clienteAlheio->id,
                'pedidosituacao_id' => $situacao->id,
                'setor_id' => $setor->id,
                'itens' => [['produto_id' => $produto->id, 'quantidade' => 1]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cliente_id');
    }

    public function test_pedido_recusa_produto_de_outra_empresa(): void
    {
        [$user, $empresa, $setor, , $situacao] = $this->cenario();

        $outra = Empresa::factory()->create(['grupo_id' => $empresa->grupo_id]);
        $produtoAlheio = Produto::factory()->create([
            'empresa_id' => $outra->id, 'grupo_id' => $outra->grupo_id, 'preco_venda' => 50,
        ]);
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/pedidos', [
                'cliente_id' => $cliente->id,
                'pedidosituacao_id' => $situacao->id,
                'setor_id' => $setor->id,
                'itens' => [['produto_id' => $produtoAlheio->id, 'quantidade' => 1]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('itens.0.produto_id');
    }

    /**
     * `convenio_id` aponta para OUTRO cliente: sem escopo, uma empresa amarrava
     * seu cliente ao convênio de um cliente alheio.
     */
    public function test_cliente_recusa_convenio_de_outra_empresa(): void
    {
        [$user, $empresa] = $this->cenario();

        $outra = Empresa::factory()->create(['grupo_id' => $empresa->grupo_id]);
        $conveniadoAlheio = Cliente::factory()->create([
            'empresa_id' => $outra->id, 'grupo_id' => $outra->grupo_id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/clientes', [
                'nome' => 'Cliente novo',
                'convenio_id' => $conveniadoAlheio->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('convenio_id');
    }

    /** O caminho legítimo continua funcionando — a regra não pode virar bloqueio. */
    public function test_pedido_da_propria_empresa_continua_valendo(): void
    {
        [$user, $empresa, $setor, $produto, $situacao] = $this->cenario();
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/pedidos', [
                'cliente_id' => $cliente->id,
                'pedidosituacao_id' => $situacao->id,
                'setor_id' => $setor->id,
                'itens' => [['produto_id' => $produto->id, 'quantidade' => 1]],
            ])
            ->assertCreated();
    }
}
