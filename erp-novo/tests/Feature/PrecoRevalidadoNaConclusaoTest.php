<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Models\AuditLog;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F4-06 — o preço é reconferido no momento em que a venda se concretiza.
 *
 * A alçada de desconto roda na criação do item, e é sólida: o piso do produto
 * vale para todos, inclusive para desconto já aprovado pela Central.
 *
 * O que faltava: entre **criar** e **concluir** pode passar dias, e nesse
 * intervalo o `preco_venda_minimo` do produto pode ter subido. O pedido então
 * conclui abaixo do piso — sem que ninguém tenha feito nada errado, e sem que
 * ninguém saiba.
 *
 * ## Registra, não bloqueia
 *
 * Quando o efeito concretiza, a mercadoria em geral **já saiu** — o entregador
 * já entregou. Recusar a conclusão deixaria o pedido num limbo: estoque físico
 * baixado na rua e o sistema dizendo que a venda não aconteceu.
 *
 * O que se faz é registrar na trilha, com o motivo. A margem perdida vira um
 * fato consultável — *"quais vendas fecharam abaixo do piso, e quanto custou"* —
 * em vez de sumir.
 */
class PrecoRevalidadoNaConclusaoTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Empresa, Produto, Cliente, PedidoSituacao, PedidoSituacao, Setor} */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $setor = Setor::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Botijão P13', 'preco_venda' => 100, 'preco_venda_minimo' => 80,
        ]);
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        $pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)
            ->create(['grupo_id' => $empresa->grupo_id]);
        $concluido = PedidoSituacao::factory()->efeito(EfeitoPedido::CONCLUIDO)
            ->create(['grupo_id' => $empresa->grupo_id]);

        return [$empresa, $produto, $cliente, $pendente, $concluido, $setor];
    }

    private function pedido(Empresa $e, Cliente $c, PedidoSituacao $s, Produto $p, Setor $setor, float $preco)
    {
        return app(PedidoService::class)->criar([
            'empresa_id' => $e->id, 'grupo_id' => $e->grupo_id,
            'cliente_id' => $c->id, 'pedidosituacao_id' => $s->id, 'setor_id' => $setor->id,
        ], [['produto_id' => $p->id, 'quantidade' => 1, 'preco_unitario' => $preco]]);
    }

    private function alertas(): int
    {
        return AuditLog::query()->where('acao', 'pedido.preco_abaixo_do_minimo')->count();
    }

    /**
     * O caso da tarefa: o preço era válido quando o pedido nasceu, e o mínimo
     * subiu antes de ele concluir.
     */
    public function test_minimo_que_subiu_depois_e_registrado_na_conclusao(): void
    {
        [$empresa, $produto, $cliente, $pendente, $concluido, $setor] = $this->cenario();
        app(EstoqueService::class)->entrada($setor->id, $produto->id, 10, 50);

        // Nasce a 85, acima do mínimo de 80 — válido.
        $pedido = $this->pedido($empresa, $cliente, $pendente, $produto, $setor, 85);
        $this->assertSame(0, $this->alertas());

        // O dono sobe o piso.
        $produto->update(['preco_venda_minimo' => 90]);

        app(PedidoService::class)->mudarSituacao($pedido, $concluido->id);

        $this->assertSame(1, $this->alertas(), 'a venda abaixo do piso vira fato consultável');
    }

    /** Registra, NÃO bloqueia: a mercadoria já saiu. */
    public function test_conclusao_nao_e_bloqueada(): void
    {
        [$empresa, $produto, $cliente, $pendente, $concluido, $setor] = $this->cenario();
        app(EstoqueService::class)->entrada($setor->id, $produto->id, 10, 50);

        $pedido = $this->pedido($empresa, $cliente, $pendente, $produto, $setor, 85);
        $produto->update(['preco_venda_minimo' => 90]);

        $concluidoPedido = app(PedidoService::class)->mudarSituacao($pedido, $concluido->id);

        $this->assertSame($concluido->id, (int) $concluidoPedido->pedidosituacao_id);
        $this->assertTrue((bool) $concluidoPedido->estoque_movimentado, 'o efeito foi aplicado');
    }

    /** Preço acima do mínimo não gera ruído — um alerta que sempre dispara não é lido. */
    public function test_preco_acima_do_minimo_nao_gera_alerta(): void
    {
        [$empresa, $produto, $cliente, $pendente, $concluido, $setor] = $this->cenario();
        app(EstoqueService::class)->entrada($setor->id, $produto->id, 10, 50);

        $pedido = $this->pedido($empresa, $cliente, $pendente, $produto, $setor, 95);
        app(PedidoService::class)->mudarSituacao($pedido, $concluido->id);

        $this->assertSame(0, $this->alertas());
    }

    /** Produto sem piso declarado não tem o que conferir. */
    public function test_produto_sem_minimo_nao_gera_alerta(): void
    {
        [$empresa, $produto, $cliente, $pendente, $concluido, $setor] = $this->cenario();
        $produto->update(['preco_venda_minimo' => null]);
        app(EstoqueService::class)->entrada($setor->id, $produto->id, 10, 50);

        $pedido = $this->pedido($empresa, $cliente, $pendente, $produto, $setor, 10);
        app(PedidoService::class)->mudarSituacao($pedido, $concluido->id);

        $this->assertSame(0, $this->alertas());
    }

    /** O alerta diz QUAL item e QUANTO — sem isso não é acionável. */
    public function test_o_alerta_identifica_o_item_e_os_valores(): void
    {
        [$empresa, $produto, $cliente, $pendente, $concluido, $setor] = $this->cenario();
        app(EstoqueService::class)->entrada($setor->id, $produto->id, 10, 50);

        $pedido = $this->pedido($empresa, $cliente, $pendente, $produto, $setor, 85);
        $produto->update(['preco_venda_minimo' => 90]);
        app(PedidoService::class)->mudarSituacao($pedido, $concluido->id);

        $motivo = (string) AuditLog::query()
            ->where('acao', 'pedido.preco_abaixo_do_minimo')->value('motivo');

        $this->assertStringContainsString('Botijão P13', $motivo);
        $this->assertStringContainsString('85,00', $motivo);
        $this->assertStringContainsString('90,00', $motivo);
    }
}
