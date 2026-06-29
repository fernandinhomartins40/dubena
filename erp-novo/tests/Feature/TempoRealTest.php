<?php

namespace Tests\Feature;

use App\Domain\Cobranca\Events\PixConfirmado;
use App\Domain\Cobranca\PixService;
use App\Domain\Estoque\EstoqueService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\Events\PedidoStatusAtualizado;
use App\Domain\Pedido\PedidoService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * P5 — Tempo real (broadcasting). Valida que a mudança de status do pedido e a
 * confirmação do PIX EMITEM eventos ShouldBroadcast nos canais privados certos
 * (empresa.{id}.pedidos e pedido.{id}), e que a autorização de canal isola por
 * tenant (o cliente só entra no canal do SEU pedido).
 */
class TempoRealTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $user;

    private Setor $setor;

    private Produto $produto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->user = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'support' => true,
        ]);
        $this->setor = Setor::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $this->produto = Produto::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'preco_venda' => 100]);
        app(EstoqueService::class)->entrada($this->setor->id, $this->produto->id, 100, 10);
    }

    private function pedido(): Pedido
    {
        $cliente = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);

        return app(PedidoService::class)->criar([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $pendente->id, 'setor_id' => $this->setor->id,
        ], [['produto_id' => $this->produto->id, 'quantidade' => 1]]);
    }

    public function test_mudar_situacao_emite_evento_de_tempo_real(): void
    {
        Event::fake([PedidoStatusAtualizado::class]);

        $pedido = $this->pedido();
        $entregue = PedidoSituacao::factory()->efeito(EfeitoPedido::CONCLUIDO)->create(['grupo_id' => $this->empresa->grupo_id]);

        app(PedidoService::class)->mudarSituacao($pedido, $entregue->id);

        Event::assertDispatched(PedidoStatusAtualizado::class, function (PedidoStatusAtualizado $e) use ($pedido) {
            return $e->pedidoId === $pedido->id && $e->empresaId === $this->empresa->id;
        });
    }

    public function test_evento_de_pedido_transmite_nos_canais_da_empresa_e_do_pedido(): void
    {
        $pedido = $this->pedido();
        $evento = new PedidoStatusAtualizado($pedido->load('situacao'));

        $canais = collect($evento->broadcastOn())->map(fn (PrivateChannel $c) => $c->name);
        $this->assertTrue($canais->contains("private-empresa.{$this->empresa->id}.pedidos"));
        $this->assertTrue($canais->contains("private-pedido.{$pedido->id}"));
        $this->assertSame('pedido.status', $evento->broadcastAs());
        $this->assertSame($pedido->id, $evento->broadcastWith()['pedido_id']);
    }

    public function test_pix_confirmado_emite_evento_no_canal_do_pedido(): void
    {
        Event::fake([PixConfirmado::class]);

        $pedido = $this->pedido();
        $pix = app(PixService::class);
        $cobranca = $pix->criarCobrancaPedido($pedido);

        $pix->processarWebhook(['txid' => $cobranca->txid, 'valor' => (float) $cobranca->valor]);

        Event::assertDispatched(PixConfirmado::class, function (PixConfirmado $e) use ($pedido, $cobranca) {
            return $e->cobrancaId === $cobranca->id && $e->pedidoId === $pedido->id;
        });
    }

    public function test_pix_reentregue_nao_emite_evento_de_novo(): void
    {
        $pedido = $this->pedido();
        $pix = app(PixService::class);
        $cobranca = $pix->criarCobrancaPedido($pedido);
        $pix->processarWebhook(['txid' => $cobranca->txid, 'valor' => (float) $cobranca->valor]);

        // 2ª entrega do mesmo webhook (idempotente): NÃO deve emitir de novo.
        Event::fake([PixConfirmado::class]);
        $pix->processarWebhook(['txid' => $cobranca->txid, 'valor' => (float) $cobranca->valor]);
        Event::assertNotDispatched(PixConfirmado::class);
    }

    public function test_autorizacao_de_canal_isola_por_tenant_e_posse(): void
    {
        // Cliente A com user vinculado, dono do pedido.
        $clienteUser = User::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $cliente = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'user_id' => $clienteUser->id]);
        $pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);
        $pedido = app(PedidoService::class)->criar([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $pendente->id, 'setor_id' => $this->setor->id,
        ], [['produto_id' => $this->produto->id, 'quantidade' => 1]]);

        $outraEmpresa = Empresa::factory()->create();
        $intruso = User::factory()->create(['empresa_id' => $outraEmpresa->id, 'grupo_id' => $outraEmpresa->grupo_id]);

        // Testa a LÓGICA de autorização do canal diretamente (independe do driver de
        // broadcast em teste): chama o callback registrado em routes/channels.php.
        // É a 2ª barreira do sigilo no tempo real.
        // Dono autoriza; intruso de outra empresa é barrado.
        $this->assertTrue($this->resolverCanal('pedido.{pedidoId}', $clienteUser, ['pedidoId' => $pedido->id]));
        $this->assertFalse($this->resolverCanal('pedido.{pedidoId}', $intruso, ['pedidoId' => $pedido->id]));

        // Canal da empresa: usuário da empresa autoriza; intruso não.
        $this->assertTrue($this->resolverCanal('empresa.{empresaId}.pedidos', $clienteUser, ['empresaId' => $this->empresa->id]));
        $this->assertFalse($this->resolverCanal('empresa.{empresaId}.pedidos', $intruso, ['empresaId' => $this->empresa->id]));
    }

    /**
     * Resolve o callback de autorização de um canal (registrado em channels.php) e
     * o executa para um usuário, retornando o booleano de autorização.
     *
     * @param  array<string,mixed>  $params
     */
    private function resolverCanal(string $padrao, User $user, array $params): bool
    {
        $broadcaster = app(Broadcaster::class);
        $ref = new \ReflectionClass($broadcaster);
        $prop = $ref->getProperty('channels');
        $prop->setAccessible(true);
        $channels = $prop->getValue($broadcaster);

        $this->assertArrayHasKey($padrao, $channels, "Canal {$padrao} deve estar registrado.");
        $callback = $channels[$padrao];

        return (bool) $callback($user, ...array_values($params));
    }
}
