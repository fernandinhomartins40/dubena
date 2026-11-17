<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoComprovacao;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PIE P2-C-05 (rodada 3) — reenvio da conclusão da entrega.
 *
 * O payload é o LITERAL que o app manda (`entrega.service.ts` → fila
 * multipart): `recebido_por`, `latitude`/`longitude` como texto, `foto` e
 * `assinatura` como arquivo, e o cabeçalho `Idempotency-Key` gerado no aparelho.
 *
 * Antes: o 1º envio estourava os 20 s com a foto mas gravava; o reenvio dava 422
 * ("o pedido mudou de situação") e deixava um arquivo órfão no disco.
 */
class PieEntregadorConclusaoReenvioTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $entregador;

    private PedidoSituacao $pendente;

    private Setor $setor;

    private Produto $produto;

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

    /** O que o app manda, campo a campo (FormData remontado pela fila). */
    private function concluir(Pedido $p, ?string $chave)
    {
        $headers = ['Accept' => 'application/json'];
        if ($chave !== null) {
            $headers['Idempotency-Key'] = $chave;
        }

        return $this->actingAs($this->entregador, 'sanctum')
            ->withHeaders($headers)
            ->post("/api/app/v1/entregador/pedidos/{$p->id}/concluir", [
                'recebido_por' => 'Ana',
                'latitude' => '-25.39',
                'longitude' => '-51.46',
                'foto' => UploadedFile::fake()->image('abc.jpg'),
                'assinatura' => UploadedFile::fake()->image('assinatura.png'),
            ]);
    }

    private function arquivosNoDisco(Pedido $p): int
    {
        return count(Storage::disk('local')->allFiles("entregas/{$p->empresa_id}/{$p->id}"));
    }

    public function test_reenvio_com_a_mesma_chave_devolve_a_primeira_resposta_sem_novo_arquivo(): void
    {
        $p = $this->pedido();
        $chave = '7f1c2a9e-0b7d-4c8e-9a51-3d2f4e6a8b10';

        $primeira = $this->concluir($p, $chave)->assertCreated();
        $segunda = $this->concluir($p, $chave)->assertCreated();

        $this->assertSame($primeira->json('data.comprovacao_id'), $segunda->json('data.comprovacao_id'));
        $this->assertSame(1, PedidoComprovacao::where('pedido_id', $p->id)->count());
        $this->assertSame(2, $this->arquivosNoDisco($p), 'foto + assinatura, uma vez só');
    }

    public function test_reenvio_sem_chave_pelo_mesmo_entregador_devolve_a_comprovacao_existente(): void
    {
        // App antigo, ou chave perdida: o servidor ainda não pode responder 422
        // a quem acabou de concluir — e não pode gravar outra foto.
        $p = $this->pedido();

        $primeira = $this->concluir($p, null)->assertCreated();
        $segunda = $this->concluir($p, null)->assertOk();

        $this->assertSame($primeira->json('data.comprovacao_id'), $segunda->json('data.comprovacao_id'));
        $this->assertSame(1, PedidoComprovacao::where('pedido_id', $p->id)->count());
        $this->assertSame(2, $this->arquivosNoDisco($p));
    }

    public function test_pedido_cancelado_recusa_e_nao_deixa_arquivo_orfao(): void
    {
        $p = $this->pedido();
        $cancelado = PedidoSituacao::factory()->efeito(EfeitoPedido::CANCELADO)->create(['grupo_id' => $this->empresa->grupo_id]);
        $p->forceFill(['pedidosituacao_id' => $cancelado->id])->save();

        $this->concluir($p, 'chave-cancelado-1')->assertStatus(422);

        $this->assertSame(0, PedidoComprovacao::where('pedido_id', $p->id)->count());
        $this->assertSame(0, $this->arquivosNoDisco($p), 'a foto do cliente não pode ficar no disco sem comprovação');
    }

    public function test_outro_entregador_nao_herda_a_comprovacao_alheia(): void
    {
        // Guarda: a resposta "já concluído" vale só para quem concluiu. Pedido
        // concluído por outro caminho (painel) continua recusando.
        $p = $this->pedido();
        $concluida = PedidoSituacao::where('grupo_id', $this->empresa->grupo_id)->where('efeito', EfeitoPedido::CONCLUIDO->value)->first();
        app(PedidoService::class)->mudarSituacao($p, $concluida->id, null);

        $this->concluir($p, null)->assertStatus(422);
        $this->assertSame(0, $this->arquivosNoDisco($p));
    }
}
