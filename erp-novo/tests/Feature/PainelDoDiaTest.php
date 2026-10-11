<?php

namespace Tests\Feature;

use App\Domain\Pedido\EfeitoPedido;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "Para hoje" — o painel diz o que pede atenção, e só para quem pode ver.
 */
class PainelDoDiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_conta_o_que_pede_atencao_hoje(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $doGrupo = ['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id];
        $cliente = Cliente::factory()->create($doGrupo);
        $aberta = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $empresa->grupo_id]);
        $cancelada = PedidoSituacao::factory()->efeito(EfeitoPedido::CANCELADO)->create(['grupo_id' => $empresa->grupo_id]);

        Pedido::query()->create($doGrupo + ['cliente_id' => $cliente->id, 'pedidosituacao_id' => $aberta->id, 'datahora' => now()->subDay(), 'valor_venda' => 0]);
        Pedido::query()->create($doGrupo + ['cliente_id' => $cliente->id, 'pedidosituacao_id' => $aberta->id, 'datahora' => now(), 'valor_venda' => 110]);
        // Cancelado hoje não é venda de hoje.
        Pedido::query()->create($doGrupo + ['cliente_id' => $cliente->id, 'pedidosituacao_id' => $cancelada->id, 'datahora' => now(), 'valor_venda' => 999]);

        $titulo = fn (string $pr) => DB::table('financeiros')->insertGetId($doGrupo + ['pagarreceber' => $pr, 'valor' => 100, 'cancelado' => false, 'created_at' => now(), 'updated_at' => now()]);
        $parcela = fn (int $f, string $venc, float $valor, bool $baixado = false) => DB::table('financeiroparcelas')->insert(['financeiro_id' => $f, 'empresa_id' => $empresa->id, 'numero' => random_int(1, 999), 'vencimento' => $venc, 'valor' => $valor, 'baixado' => $baixado, 'created_at' => now(), 'updated_at' => now()]);
        $r = $titulo('R');
        $parcela($r, today()->toDateString(), 50);
        $parcela($r, today()->subDays(5)->toDateString(), 30);
        $parcela($r, today()->subDays(9)->toDateString(), 70, true); // já recebida: não conta
        $parcela($titulo('P'), today()->toDateString(), 20);

        $resp = $this->actingAs($user, 'sanctum')->getJson('/api/admin/dashboard/hoje')->assertOk();

        $resp->assertJsonPath('data.pedidos.em_aberto', 2)
            ->assertJsonPath('data.pedidos.hoje', 1)
            ->assertJsonPath('data.pedidos.hoje_valor', 110)
            ->assertJsonPath('data.financeiro.receber_hoje.quantidade', 1)
            ->assertJsonPath('data.financeiro.receber_hoje.valor', 50)
            ->assertJsonPath('data.financeiro.receber_vencido.valor', 30)
            ->assertJsonPath('data.financeiro.pagar_hoje.valor', 20);
        $this->assertNotNull($resp->json('data.pedidos.mais_antigo'));
    }

    /** Sem permissão do módulo o bloco não vem — nem como zero. */
    public function test_bloco_sem_permissao_fica_de_fora(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->semPapel()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $data = $this->actingAs($user, 'sanctum')->getJson('/api/admin/dashboard/hoje')->assertOk()->json('data');

        $this->assertArrayNotHasKey('pedidos', $data);
        $this->assertArrayNotHasKey('financeiro', $data);
        $this->assertArrayNotHasKey('cadastros_a_revisar', $data);
    }

    public function test_exige_autenticacao(): void
    {
        $this->getJson('/api/admin/dashboard/hoje')->assertStatus(401);
    }
}
