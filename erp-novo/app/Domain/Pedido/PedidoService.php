<?php

namespace App\Domain\Pedido;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Financeiro\FinanceiroService;
use App\Domain\Pedido\Events\PedidoStatusAtualizado;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PedidoService (N4) — orquestra a venda.
 *
 * A regra central do legado (matriz situação×operação×setor que decidia INSERE/
 * EXCLUI financeiro e SAÍDA/ENTRADA estoque) vira uma MÁQUINA DE ESTADOS EXPLÍCITA:
 * a transição de efeito (PENDENTE↔CONCLUIDO↔CANCELADO) decide a movimentação.
 *
 *   → CONCLUIDO : baixa estoque (SAÍDA) [+ gera financeiro — gancho N5]
 *   → CANCELADO : devolve estoque (ENTRADA) [+ estorna financeiro — N5]
 *   → PENDENTE  : sem efeito patrimonial
 *
 * Idempotência: `estoque_movimentado` evita baixar/devolver em dobro.
 */
class PedidoService
{
    public function __construct(
        private EstoqueService $estoque,
        private FinanceiroService $financeiro,
    ) {}

    /** @param array<string, mixed> $dados @param list<array<string,mixed>> $itens */
    public function criar(array $dados, array $itens): Pedido
    {
        return DB::transaction(function () use ($dados, $itens) {
            $situacao = $this->situacao((int) $dados['pedidosituacao_id']);

            $pedido = Pedido::create(array_merge($dados, [
                'datahora' => $dados['datahora'] ?? now(),
                'estoque_movimentado' => false,
            ]));

            $this->sincronizarItens($pedido, $itens);
            $this->recalcularTotais($pedido);
            $this->registrarHistorico($pedido, $situacao, $dados['user_id'] ?? null);

            // Se já nasce CONCLUIDO, aplica o efeito (baixa estoque).
            $this->aplicarEfeito($pedido, $situacao, null, $dados['user_id'] ?? null);

            return $pedido->refresh()->load('itens');
        });
    }

    /** @param array<string, mixed> $dados @param list<array<string,mixed>>|null $itens */
    public function atualizar(Pedido $pedido, array $dados, ?array $itens = null): Pedido
    {
        return DB::transaction(function () use ($pedido, $dados, $itens) {
            // Não permite editar itens de pedido já concretizado (estoque baixado).
            if ($itens !== null) {
                if ($pedido->estoque_movimentado) {
                    throw ValidationException::withMessages(['itens' => 'Pedido concretizado: itens não podem ser alterados.']);
                }
                $this->sincronizarItens($pedido, $itens);
            }

            $pedido->fill($dados);
            $pedido->save();
            $this->recalcularTotais($pedido);

            return $pedido->refresh()->load('itens');
        });
    }

    /**
     * Transição de situação — o ponto onde a máquina de estados age.
     */
    public function mudarSituacao(Pedido $pedido, int $novaSituacaoId, ?int $userId = null): Pedido
    {
        $atualizado = DB::transaction(function () use ($pedido, $novaSituacaoId, $userId) {
            $anterior = $pedido->situacao; // efeito atual
            $nova = $this->situacao($novaSituacaoId);

            $pedido->pedidosituacao_id = $nova->id;
            $pedido->datahora_acao = now();
            $pedido->save();

            $this->registrarHistorico($pedido, $nova, $userId);
            $this->aplicarEfeito($pedido, $nova, $anterior?->efeito, $userId);

            return $pedido->refresh();
        });

        // Tempo real (P5): notifica o canal da empresa e do pedido APÓS o commit
        // (não emite em caso de rollback). Em dev/CI (broadcast=null/log) é no-op.
        PedidoStatusAtualizado::dispatch($atualizado->load('situacao'));

        return $atualizado;
    }

    public function excluir(Pedido $pedido): void
    {
        if ($pedido->estoque_movimentado) {
            throw ValidationException::withMessages(['pedido' => 'Pedido concretizado não pode ser excluído; cancele-o primeiro.']);
        }
        $pedido->delete();
    }

    /**
     * Aplica o efeito da NOVA situação considerando o efeito ANTERIOR.
     * - vira CONCLUIDO (e ainda não movimentou) → SAÍDA de estoque.
     * - vira CANCELADO (e havia movimentado)    → ENTRADA (devolve) estoque.
     */
    private function aplicarEfeito(Pedido $pedido, PedidoSituacao $nova, ?EfeitoPedido $efeitoAnterior, ?int $userId): void
    {
        $efeito = $nova->efeito;

        if ($efeito->concretiza() && ! $pedido->estoque_movimentado) {
            $this->baixarEstoque($pedido, $userId);
            $financeiro = $this->financeiro->gerarDoPedido($pedido);
            $pedido->forceFill([
                'estoque_movimentado' => true,
                'financeiro_id' => $financeiro?->id,
            ])->save();
        }

        if ($efeito->cancela() && $pedido->estoque_movimentado) {
            $this->devolverEstoque($pedido, $userId);
            $this->financeiro->estornarDoPedido($pedido);
            $pedido->forceFill(['estoque_movimentado' => false, 'financeiro_id' => null])->save();
        }
    }

    private function baixarEstoque(Pedido $pedido, ?int $userId): void
    {
        if (! $pedido->setor_id) {
            throw ValidationException::withMessages(['setor_id' => 'Defina o setor para baixar o estoque.']);
        }
        foreach ($pedido->itens as $item) {
            $this->estoque->saida($pedido->setor_id, $item->produto_id, (float) $item->quantidade, 'pedido', $pedido->id, $userId);
        }
    }

    private function devolverEstoque(Pedido $pedido, ?int $userId): void
    {
        foreach ($pedido->itens as $item) {
            $this->estoque->entrada($pedido->setor_id, $item->produto_id, (float) $item->quantidade, null, 'pedido-cancelado', $pedido->id, $userId);
        }
    }

    /** @param list<array<string,mixed>> $itens */
    private function sincronizarItens(Pedido $pedido, array $itens): void
    {
        $pedido->itens()->delete();
        foreach ($itens as $i) {
            $produto = Produto::query()->findOrFail($i['produto_id']);
            $qtd = (float) $i['quantidade'];
            $preco = isset($i['preco_unitario']) ? (float) $i['preco_unitario'] : (float) $produto->preco_venda;
            $desc = (float) ($i['desconto'] ?? 0);

            $pedido->itens()->create([
                'produto_id' => $produto->id,
                'quantidade' => $qtd,
                'preco_unitario' => $preco,
                'desconto' => $desc,
                'valor_total' => round($qtd * $preco - $desc, 2),
            ]);
        }
    }

    private function recalcularTotais(Pedido $pedido): void
    {
        $itens = $pedido->itens()->get();
        $valor = (float) $itens->sum('valor_total') + (float) $pedido->entrega_taxa;
        $desconto = (float) $itens->sum('desconto');

        $pedido->forceFill([
            'valor_venda' => round($valor, 2),
            'valor_desconto' => round($desconto, 2),
        ])->save();
    }

    private function registrarHistorico(Pedido $pedido, PedidoSituacao $situacao, ?int $userId): void
    {
        $pedido->historico()->create([
            'pedidosituacao_id' => $situacao->id,
            'user_id' => $userId,
            'efeito' => $situacao->efeito,
            'datahora' => Carbon::now(),
        ]);
    }

    private function situacao(int $id): PedidoSituacao
    {
        return PedidoSituacao::query()->findOrFail($id);
    }
}
