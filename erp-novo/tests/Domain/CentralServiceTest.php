<?php

namespace Tests\Domain;

use App\Domain\Logistica\CentralService;
use App\Domain\Logistica\JornadaService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Domain\Tenant\TenantContext;
use App\Models\Cliente\Cliente;
use App\Models\Estoque\Setor;
use App\Models\Logistica\PedidoAtribuicao;
use App\Models\Monitora\Veiculo;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * L1 — CentralService: fila de distribuição, atribuição/redistribuição com trilha,
 * bloqueio de entregador e carga.
 */
class CentralServiceTest extends TestCase
{
    use RefreshDatabase;

    private CentralService $central;

    private PedidoService $pedidos;

    private Setor $setor;

    private Produto $produto;

    private Cliente $cliente;

    private int $empresaId;

    private int $grupoId;

    private User $entregador;

    private ?PedidoSituacao $situacaoPendente = null;

    protected function setUp(): void
    {
        parent::setUp();
        // Fake SELETIVO (broadcasts): Event::fake() global mataria os model events
        // do Eloquent (creating da BelongsToTenant -> empresa_id ficaria null).
        Event::fake([
            \App\Domain\Logistica\Events\PedidoEntrouNaFila::class,
            \App\Domain\Logistica\Events\PedidoAtribuido::class,
            \App\Domain\Pedido\Events\PedidoStatusAtualizado::class,
        ]);

        $this->central = app(CentralService::class);
        $this->pedidos = app(PedidoService::class);

        $this->setor = Setor::factory()->create();
        $this->empresaId = $this->setor->empresa_id;
        $this->grupoId = $this->setor->grupo_id;
        app(TenantContext::class)->set($this->empresaId, $this->grupoId);

        $this->produto = Produto::factory()->create(['empresa_id' => $this->empresaId, 'grupo_id' => $this->grupoId, 'preco_venda' => 100]);
        $this->cliente = Cliente::factory()->create(['empresa_id' => $this->empresaId, 'grupo_id' => $this->grupoId]);
        app(\App\Domain\Estoque\EstoqueService::class)->entrada($this->setor->id, $this->produto->id, 100, 10.0);

        $this->entregador = User::factory()->create(['empresa_id' => $this->empresaId, 'grupo_id' => $this->grupoId]);
        $this->operador = User::factory()->create(['empresa_id' => $this->empresaId, 'grupo_id' => $this->grupoId]);
    }

    private User $operador;

    private function pedidoPendente(): Pedido
    {
        // Uma única situação PENDENTE por grupo (unique grupo_id+descricao).
        $this->situacaoPendente ??= PedidoSituacao::factory()
            ->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->grupoId]);

        return $this->pedidos->criar([
            'empresa_id' => $this->empresaId,
            'grupo_id' => $this->grupoId,
            'cliente_id' => $this->cliente->id,
            'pedidosituacao_id' => $this->situacaoPendente->id,
            'setor_id' => $this->setor->id,
        ], [['produto_id' => $this->produto->id, 'quantidade' => 1, 'preco_unitario' => 100]]);
    }

    public function test_fila_lista_apenas_pendentes_sem_entregador(): void
    {
        $this->pedidoPendente();
        $atribuido = $this->pedidoPendente();
        $atribuido->forceFill(['entregador_user_id' => $this->entregador->id])->save();

        $fila = $this->central->filaDistribuicao($this->empresaId);

        $this->assertCount(1, $fila);
    }

    public function test_atribuir_grava_entregador_e_registra_trilha(): void
    {
        $pedido = $this->pedidoPendente();

        $this->central->atribuir($pedido, $this->entregador->id, null, $this->operador->id);

        $this->assertSame($this->entregador->id, $pedido->refresh()->entregador_user_id);
        $this->assertDatabaseHas('pedido_atribuicoes', [
            'pedido_id' => $pedido->id,
            'para_entregador_user_id' => $this->entregador->id,
            'acao' => 'atribuir',
            'operador_user_id' => $this->operador->id,
        ]);
    }

    public function test_redistribuir_registra_de_para(): void
    {
        $pedido = $this->pedidoPendente();
        $outro = User::factory()->create(['empresa_id' => $this->empresaId, 'grupo_id' => $this->grupoId]);

        $this->central->atribuir($pedido, $this->entregador->id);
        $this->central->redistribuir($pedido, $outro->id, $this->operador->id, 'Trocou de rota');

        $this->assertSame($outro->id, $pedido->refresh()->entregador_user_id);
        $trilha = PedidoAtribuicao::where('pedido_id', $pedido->id)->where('acao', 'redistribuir')->first();
        $this->assertNotNull($trilha);
        $this->assertSame($this->entregador->id, $trilha->de_entregador_user_id);
        $this->assertSame($outro->id, $trilha->para_entregador_user_id);
    }

    public function test_entregador_bloqueado_nao_recebe_atribuicao(): void
    {
        $pedido = $this->pedidoPendente();
        $this->central->bloquearEntregador($this->empresaId, $this->entregador->id, $this->operador->id, 'Fora do ar');

        $this->expectException(ValidationException::class);
        $this->central->atribuir($pedido, $this->entregador->id);
    }

    public function test_desbloquear_permite_atribuir_de_novo(): void
    {
        $pedido = $this->pedidoPendente();
        $this->central->bloquearEntregador($this->empresaId, $this->entregador->id, $this->operador->id, 'x');
        $this->central->desbloquearEntregador($this->empresaId, $this->entregador->id);

        $this->central->atribuir($pedido, $this->entregador->id);
        $this->assertSame($this->entregador->id, $pedido->refresh()->entregador_user_id);
    }

    public function test_carga_por_entregador_conta_pedidos_ativos(): void
    {
        $p1 = $this->pedidoPendente();
        $p2 = $this->pedidoPendente();
        $this->central->atribuir($p1, $this->entregador->id);
        $this->central->atribuir($p2, $this->entregador->id);

        $carga = $this->central->cargaPorEntregador($this->empresaId);

        $this->assertSame(2, $carga[$this->entregador->id] ?? 0);
    }

    public function test_priorizar_marca_urgente_e_ordena_primeiro(): void
    {
        $normal = $this->pedidoPendente();
        $urgente = $this->pedidoPendente();
        $this->central->priorizar($urgente, true);

        $fila = $this->central->filaDistribuicao($this->empresaId);

        $this->assertSame($urgente->id, $fila->first()->id);
    }
}
