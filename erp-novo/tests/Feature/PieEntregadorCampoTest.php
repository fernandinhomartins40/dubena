<?php

namespace Tests\Feature;

use App\Domain\Caixa\MaloteService;
use App\Domain\Estoque\EstoqueService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\Satelite\ValeGas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PIE rodada 2 — dinheiro em campo, no app do entregador.
 *
 * P2-C-01: "Recusar" num pedido concluído desvinculava o entregador e tirava o
 * pedido do malote dele. P2-C-02: a verificação do Vale Gás aprovava vale não
 * pago, expirado e vencido. Nasceram das sondas da auditoria.
 */
class PieEntregadorCampoTest extends TestCase
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

    public function test_p2_c_01_recusar_pedido_concluido_e_recusado_e_o_malote_continua_com_ele(): void
    {
        $p = $this->pedido();
        $this->actingAs($this->entregador, 'sanctum')
            ->postJson("/api/app/v1/entregador/pedidos/{$p->id}/concluir", [
                'assinatura' => UploadedFile::fake()->image('assinatura.png'),
            ])->assertCreated();

        $this->actingAs($this->entregador, 'sanctum')
            ->postJson("/api/app/v1/entregador/pedidos/{$p->id}/recusar")
            ->assertStatus(422);
        $this->actingAs($this->entregador, 'sanctum')
            ->postJson("/api/app/v1/entregador/pedidos/{$p->id}/aceitar")
            ->assertStatus(422);

        $this->assertSame($this->entregador->id, (int) $p->fresh()->entregador_user_id);
        $hoje = now()->toDateString();
        $malote = app(MaloteService::class)->conferir($this->empresa->id, $hoje, $hoje, null, $this->entregador->id);
        $this->assertNotEmpty(collect($malote)->flatten()->filter()->all());
    }

    public function test_p2_c_01_recusar_pedido_em_aberto_continua_funcionando(): void
    {
        $p = $this->pedido();

        $this->actingAs($this->entregador, 'sanctum')
            ->postJson("/api/app/v1/entregador/pedidos/{$p->id}/recusar")
            ->assertSuccessful();
        $this->assertNull($p->fresh()->entregador_user_id);
    }

    public function test_p2_c_02_so_vale_pago_e_dentro_da_validade_passa(): void
    {
        $casos = [
            'EMITIDO' => ['situacao' => 'EMITIDO', 'validade' => now()->addMonth()->toDateString(), 'ok' => false],
            'EXPIRADO' => ['situacao' => 'EXPIRADO', 'validade' => now()->addMonth()->toDateString(), 'ok' => false],
            'PAGO vencido' => ['situacao' => 'PAGO', 'validade' => now()->subMonth()->toDateString(), 'ok' => false],
            'PAGO válido' => ['situacao' => 'PAGO', 'validade' => now()->addMonth()->toDateString(), 'ok' => true],
        ];

        $i = 0;
        foreach ($casos as $rotulo => $c) {
            $i++;
            ValeGas::query()->create([
                'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
                'codigo' => "VG-PIE-{$i}", 'valor' => 120, 'validade' => $c['validade'], 'situacao' => $c['situacao'],
            ]);

            $status = $this->actingAs($this->entregador, 'sanctum')
                ->postJson('/api/app/v1/entregador/vale-gas/verificar', ['codigo' => "VG-PIE-{$i}"])->status();
            $this->assertSame($c['ok'], $status === 200, "vale {$rotulo} respondeu {$status}");
        }
    }
}
