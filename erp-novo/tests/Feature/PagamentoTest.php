<?php

namespace Tests\Feature;

use App\Domain\Caixa\CaixaService;
use App\Domain\Tenant\TenantContext;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Pedido\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** FASE C4 resto — cartão (NSU) e Gás do Povo. */
class PagamentoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->user = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'support' => true,
        ]);
        app(TenantContext::class)->set($this->empresa->id, $this->empresa->grupo_id);
    }

    public function test_cartao_credita_liquido_no_caixa(): void
    {
        $conta = app(CaixaService::class)->criarConta([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'descricao' => 'Caixa', 'saldo_inicial' => 0,
        ]);

        // 100 bruto, taxa 3% → líquido 97 no caixa.
        $r = $this->actingAs($this->user, 'sanctum')->postJson('/api/admin/cartoes', [
            'conta_id' => $conta->id, 'bandeira' => 'visa', 'nsu' => '12345',
            'valor_bruto' => 100, 'taxa_percentual' => 3,
        ])->assertStatus(201);

        $this->assertSame('97.00', $r->json('data.valor_liquido'));
        $this->assertEqualsWithDelta(97.0, (float) $conta->refresh()->saldo_atual, 0.001);
    }

    public function test_gas_do_povo_registrar_e_sacar(): void
    {
        $conta = app(CaixaService::class)->criarConta([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'descricao' => 'Caixa', 'saldo_inicial' => 0,
        ]);
        $pedido = $this->criarPedido();

        $b = $this->actingAs($this->user, 'sanctum')->postJson('/api/admin/gasdopovo', [
            'competencia' => '2026-06', 'valor' => 110,
        ])->assertStatus(201);
        $id = $b->json('data.id');

        $this->actingAs($this->user, 'sanctum')->postJson("/api/admin/gasdopovo/{$id}/sacar", [
            'pedido_id' => $pedido->id, 'conta_id' => $conta->id,
        ])->assertOk()->assertJsonPath('data.situacao', 'utilizado');

        $this->assertEqualsWithDelta(110.0, (float) $conta->refresh()->saldo_atual, 0.001);
    }

    private function criarPedido(): Pedido
    {
        $cliente = Cliente::create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'nome' => 'Cliente Teste',
        ]);
        $situacaoId = DB::table('pedidosituacoes')->insertGetId([
            'grupo_id' => $this->empresa->grupo_id, 'descricao' => 'Aberto',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return Pedido::create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $situacaoId,
            'datahora' => now(), 'valor_venda' => 110, 'valor_desconto' => 0,
        ]);
    }
}
