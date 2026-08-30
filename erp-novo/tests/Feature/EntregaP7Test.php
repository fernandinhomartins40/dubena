<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P7 (backend) — ciclo da entrega pelo app do entregador: aceite/recusa, ocorrência
 * e conclusão com comprovação (foto/assinatura em storage privado). A conclusão
 * reusa a máquina de estados (CONCLUIDO) — baixa estoque e emite evento (P5).
 */
class EntregaP7Test extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $entregador;

    private Setor $setor;

    private Produto $produto;

    private PedidoSituacao $pendente;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->empresa = Empresa::factory()->create();
        $this->entregador = User::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $this->setor = Setor::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $this->produto = Produto::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'preco_venda' => 100]);
        app(EstoqueService::class)->entrada($this->setor->id, $this->produto->id, 100, 10);
        $this->pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);
        // Situação concluída precisa existir para o concluir().
        PedidoSituacao::factory()->efeito(EfeitoPedido::CONCLUIDO)->create(['grupo_id' => $this->empresa->grupo_id]);
    }

    private function pedido(): Pedido
    {
        $cliente = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);

        return app(PedidoService::class)->criar([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $this->pendente->id, 'setor_id' => $this->setor->id,
            'entregador_user_id' => $this->entregador->id,
        ], [['produto_id' => $this->produto->id, 'quantidade' => 1]]);
    }

    public function test_aceitar_corrida(): void
    {
        $pedido = $this->pedido();
        $this->actingAs($this->entregador, 'sanctum')
            ->postJson("/api/app/v1/entregador/pedidos/{$pedido->id}/aceitar")
            ->assertOk()->assertJsonPath('data.id', $pedido->id);
    }

    public function test_recusar_gera_ocorrencia_e_desvincula(): void
    {
        $pedido = $this->pedido();
        $this->actingAs($this->entregador, 'sanctum')
            ->postJson("/api/app/v1/entregador/pedidos/{$pedido->id}/recusar", ['motivo' => 'Longe demais'])
            ->assertCreated();

        $this->assertDatabaseHas('pedido_ocorrencias', ['pedido_id' => $pedido->id, 'tipo' => 'recusou']);
        $this->assertDatabaseHas('pedidos', ['id' => $pedido->id, 'entregador_user_id' => null]);
    }

    public function test_registrar_ocorrencia_com_foto(): void
    {
        $pedido = $this->pedido();
        $this->actingAs($this->entregador, 'sanctum')
            ->postJson("/api/app/v1/entregador/pedidos/{$pedido->id}/ocorrencia", [
                'tipo' => 'ausente', 'descricao' => 'Cliente não estava',
                'foto' => UploadedFile::fake()->image('ocorrencia.jpg'),
            ])->assertCreated()->assertJsonPath('data.tipo', 'ausente');

        $oc = \DB::table('pedido_ocorrencias')->where('pedido_id', $pedido->id)->first();
        $this->assertNotNull($oc->foto_path);
        Storage::disk('local')->assertExists($oc->foto_path);
    }

    public function test_concluir_exige_comprovacao(): void
    {
        $pedido = $this->pedido();
        // Sem foto nem assinatura → 422.
        $this->actingAs($this->entregador, 'sanctum')
            ->postJson("/api/app/v1/entregador/pedidos/{$pedido->id}/concluir", ['recebido_por' => 'João'])
            ->assertStatus(422);
    }

    public function test_concluir_com_assinatura_grava_comprovacao_e_conclui(): void
    {
        $pedido = $this->pedido();

        $this->actingAs($this->entregador, 'sanctum')
            ->postJson("/api/app/v1/entregador/pedidos/{$pedido->id}/concluir", [
                'recebido_por' => 'Maria',
                'assinatura' => UploadedFile::fake()->image('assinatura.png'),
            ])->assertCreated()->assertJsonPath('data.concluido', true);

        // Comprovação gravada (com arquivo no disco privado).
        $comp = \DB::table('pedido_comprovacoes')->where('pedido_id', $pedido->id)->first();
        $this->assertNotNull($comp->assinatura_path);
        Storage::disk('local')->assertExists($comp->assinatura_path);

        // Pedido passou para situação de efeito CONCLUIDO (estoque baixado).
        $pedido->refresh()->load('situacao');
        $this->assertSame(EfeitoPedido::CONCLUIDO, $pedido->situacao->efeito);
        $this->assertTrue((bool) $pedido->estoque_movimentado);
    }

    public function test_pedido_de_outro_entregador_da_404(): void
    {
        $pedido = $this->pedido();
        $outro = User::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);

        $this->actingAs($outro, 'sanctum')
            ->postJson("/api/app/v1/entregador/pedidos/{$pedido->id}/aceitar")
            ->assertNotFound();
    }
}
