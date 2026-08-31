<?php

namespace Tests\Feature;

use App\Domain\Estoque\ConferenciaDeSaldo;
use App\Domain\Estoque\EstoqueService;
use App\Models\Empresa;
use App\Models\Estoque\EstoqueInventario;
use App\Models\Estoque\Setor;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F4-03 — o inventário registra quem contou e quem aprovou.
 *
 * A estrutura já existia e a efetivação já estava correta: usa o saldo derivado
 * do ledger, grava `quantidade_sistema` no momento e gera o movimento de acerto
 * — o ajuste **já era rastreável**.
 *
 * O que faltava é a outra metade: **autoria e aprovação**.
 *
 * O `user_id` do movimento de acerto diz quem apertou o botão de efetivar. Não
 * diz quem **contou** — e num inventário essas costumam ser pessoas diferentes:
 * o conferente vai ao depósito com a lista, o supervisor aprova o ajuste.
 *
 * Sem a separação, um ajuste de milhares de reais fica com um único nome, e a
 * pergunta que a auditoria faz — *"quem contou? quem autorizou?"* — não tem
 * resposta no sistema.
 */
class InventarioAutoriaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Empresa, Setor, Produto, User} */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $setor = Setor::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        return [$empresa, $setor, $produto, $user];
    }

    /** Quem informa a contagem fica registrado. */
    public function test_inventario_registra_quem_contou(): void
    {
        [, $setor, $produto, $conferente] = $this->cenario();

        $this->actingAs($conferente, 'sanctum')
            ->postJson('/api/admin/estoque/inventarios', [
                'setor_id' => $setor->id,
                'itens' => [['produto_id' => $produto->id, 'quantidade_contada' => 7]],
            ])
            ->assertCreated();

        $this->assertSame(
            $conferente->id,
            (int) EstoqueInventario::withoutTenant()->latest('id')->value('contado_por'),
        );
    }

    /** A efetivação registra quem aprovou, e quando. */
    public function test_efetivacao_registra_quem_aprovou(): void
    {
        [$empresa, $setor, $produto, $conferente] = $this->cenario();
        $supervisor = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        $inventario = EstoqueInventario::create([
            'empresa_id' => $empresa->id, 'setor_id' => $setor->id,
            'data' => now()->toDateString(), 'situacao' => 'aberto',
            'contado_por' => $conferente->id,
        ]);
        $inventario->itens()->create(['produto_id' => $produto->id, 'quantidade_contada' => 7]);

        app(EstoqueService::class)->efetivarInventario($inventario, $supervisor->id);

        $efetivado = $inventario->fresh();

        $this->assertSame($conferente->id, (int) $efetivado->contado_por);
        $this->assertSame($supervisor->id, (int) $efetivado->aprovado_por, 'quem apertou o botão autorizou');
        $this->assertNotNull($efetivado->aprovado_em);
    }

    /**
     * As duas colunas separadas deixam VISÍVEL quando é a mesma pessoa — que é
     * uma informação, não um defeito: numa revenda pequena é o normal.
     */
    public function test_mesma_pessoa_contando_e_aprovando_fica_visivel(): void
    {
        [$empresa, $setor, $produto, $user] = $this->cenario();

        $inventario = EstoqueInventario::create([
            'empresa_id' => $empresa->id, 'setor_id' => $setor->id,
            'data' => now()->toDateString(), 'situacao' => 'aberto',
            'contado_por' => $user->id,
        ]);
        $inventario->itens()->create(['produto_id' => $produto->id, 'quantidade_contada' => 7]);

        app(EstoqueService::class)->efetivarInventario($inventario, $user->id);

        $efetivado = $inventario->fresh();

        $this->assertSame((int) $efetivado->contado_por, (int) $efetivado->aprovado_por);
    }

    /**
     * O que já funcionava e não pode regredir: o ajuste gera movimento, e a
     * projeção continua fechando com o ledger depois de efetivar.
     */
    public function test_ajuste_do_inventario_mantem_a_projecao_fechando(): void
    {
        [$empresa, $setor, $produto, $user] = $this->cenario();

        app(EstoqueService::class)->entrada($setor->id, $produto->id, 10, 5);

        $inventario = EstoqueInventario::create([
            'empresa_id' => $empresa->id, 'setor_id' => $setor->id,
            'data' => now()->toDateString(), 'situacao' => 'aberto',
            'contado_por' => $user->id,
        ]);
        // Contagem física acusa 7: o sistema dizia 10.
        $inventario->itens()->create(['produto_id' => $produto->id, 'quantidade_contada' => 7]);

        app(EstoqueService::class)->efetivarInventario($inventario, $user->id);

        $item = $inventario->fresh()->itens->first();

        $this->assertSame('10.0000', (string) $item->quantidade_sistema, 'o saldo do sistema fica gravado');
        $this->assertTrue(
            app(ConferenciaDeSaldo::class)->fecha($empresa->id),
            'o acerto gerou movimento: projeção e ledger continuam batendo',
        );
    }

    /** Efetivar duas vezes não reajusta — a idempotência já existia e vale conferir. */
    public function test_efetivar_duas_vezes_nao_reajusta(): void
    {
        [$empresa, $setor, $produto, $user] = $this->cenario();
        app(EstoqueService::class)->entrada($setor->id, $produto->id, 10, 5);

        $inventario = EstoqueInventario::create([
            'empresa_id' => $empresa->id, 'setor_id' => $setor->id,
            'data' => now()->toDateString(), 'situacao' => 'aberto',
            'contado_por' => $user->id,
        ]);
        $inventario->itens()->create(['produto_id' => $produto->id, 'quantidade_contada' => 7]);

        $servico = app(EstoqueService::class);
        $servico->efetivarInventario($inventario, $user->id);
        $servico->efetivarInventario($inventario->fresh(), $user->id);

        $this->assertSame(7.0, $servico->saldoDerivado($setor->id, $produto->id));
    }
}
