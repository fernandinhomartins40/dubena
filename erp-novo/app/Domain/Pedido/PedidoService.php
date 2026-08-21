<?php

namespace App\Domain\Pedido;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Financeiro\FinanceiroService;
use App\Domain\Logistica\Events\PedidoEntrouNaFila;
use App\Domain\Logistica\Jobs\AtribuirPedidoJob;
use App\Domain\Pedido\Events\PedidoStatusAtualizado;
use App\Domain\Venda\AlcadaDescontoService;
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
        private AlcadaDescontoService $alcada,
    ) {}

    /** @param array<string, mixed> $dados @param list<array<string,mixed>> $itens */
    public function criar(array $dados, array $itens): Pedido
    {
        $pedido = DB::transaction(function () use ($dados, $itens) {
            $situacao = $this->situacao((int) $dados['pedidosituacao_id']);

            $pedido = Pedido::create(array_merge($dados, [
                'datahora' => $dados['datahora'] ?? now(),
                'estoque_movimentado' => false,
            ]));

            $this->sincronizarItens($pedido, $itens, $this->contextoAlcada($dados));
            $this->recalcularTotais($pedido);
            $this->registrarHistorico($pedido, $situacao, $dados['user_id'] ?? null);

            // Se já nasce CONCLUIDO, aplica o efeito (baixa estoque).
            $this->aplicarEfeito($pedido, $situacao, null, $dados['user_id'] ?? null);

            return $pedido->refresh()->load(['itens', 'situacao', 'cliente']);
        });

        // L2/L3 — se o pedido nasce PENDENTE, entra na FILA de distribuição:
        // avisa a Central (tempo real) e dispara a auto-atribuição (só age em modo
        // `auto`; em `sugerir`, é no-op). Fora da transação (após commit).
        if ($pedido->situacao?->efeito === EfeitoPedido::PENDENTE && $pedido->entregador_user_id === null) {
            PedidoEntrouNaFila::dispatch($pedido);
            AtribuirPedidoJob::dispatch($pedido->id, (int) $pedido->empresa_id, (int) $pedido->grupo_id);
        }

        return $pedido;
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
                // O contexto cai para o do próprio pedido quando `$dados` não
                // traz — numa edição, condição/setor costumam vir só do registro.
                $this->sincronizarItens($pedido, $itens, $this->contextoAlcada($dados, $pedido));
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
    /**
     * Contexto da alçada a partir do que já chega no pedido — assim os
     * chamadores existentes (controllers admin, app, ETL) não precisam mudar.
     *
     * `usuario` sai de `auth()->user()` e não de `user_id` dos dados: a alçada é
     * de quem está lançando AGORA, e aceitar um id do payload deixaria o
     * vendedor escolher a própria alçada — que é o buraco do legado
     * (NfwebController::savePedido:329 confia no `colaborador_id` do corpo).
     *
     * @param  array<string,mixed>  $dados
     * @return array<string,mixed>
     */
    private function contextoAlcada(array $dados, ?Pedido $pedido = null): array
    {
        return [
            'usuario' => auth()->user(),
            'setor_id' => $dados['setor_id'] ?? $pedido?->setor_id,
            'condicaopagamento_id' => $dados['condicaopagamento_id'] ?? $pedido?->condicaopagamento_id,
            'desconto_aprovado' => (bool) ($dados['desconto_aprovado'] ?? false),
        ];
    }

    /**
     * @param  array<string,mixed>  $contexto  quem lança e sob qual condição — a
     *                                         alçada é da pessoa, e o teto pode
     *                                         variar por setor/condição de pagamento
     */
    private function sincronizarItens(Pedido $pedido, array $itens, array $contexto = []): void
    {
        $pedido->itens()->delete();
        foreach ($itens as $i) {
            $produto = Produto::query()->findOrFail($i['produto_id']);
            $qtd = (float) $i['quantidade'];
            $preco = isset($i['preco_unitario']) ? (float) $i['preco_unitario'] : (float) $produto->preco_venda;
            $desc = (float) ($i['desconto'] ?? 0);

            // F2 — teto de desconto. Antes disto o desconto entrava sem nenhuma
            // verificação, reproduzindo o legado (onde o campo de preço é livre:
            // PedidoFragment2.java:80). Só verifica quando HÁ desconto: pedido a
            // preço de tabela não paga o custo da consulta.
            if ($desc > 0) {
                $this->verificarAlcada($produto, $qtd, $preco, $desc, $i, $contexto);
            }

            $pedido->itens()->create([
                'produto_id' => $produto->id,
                'quantidade' => $qtd,
                'preco_unitario' => $preco,
                'desconto' => $desc,
                'valor_total' => round($qtd * $preco - $desc, 2),
            ]);
        }
    }

    /**
     * Recusa desconto acima do teto de quem está lançando.
     *
     * Lança DomainException (→ 422 pelo handler em bootstrap/app.php), não
     * ValidationException: isto é regra de negócio negando a operação, e a
     * mensagem é para o vendedor ler. Quem quiser desconto maior usa a
     * solicitação para a Central (F3/F4), não força por aqui.
     *
     * @param  array<string,mixed>  $item
     * @param  array<string,mixed>  $contexto
     */
    private function verificarAlcada(Produto $produto, float $qtd, float $preco, float $desc, array $item, array $contexto): void
    {
        // 1) Piso do produto — vale para TODOS, inclusive desconto já aprovado
        // pela Central. `preco_venda_minimo` vem migrado do legado e é exposto na
        // API, mas nenhuma regra o aplicava: é limite do PRODUTO, não da pessoa,
        // então nem o supervisor passa por cima.
        $minimo = $produto->preco_venda_minimo !== null ? (float) $produto->preco_venda_minimo : null;
        if ($minimo !== null && $qtd > 0) {
            $unitarioFinal = ($qtd * $preco - $desc) / $qtd;
            if ($unitarioFinal < $minimo - 0.001) {
                throw new \DomainException(sprintf(
                    'Desconto deixaria "%s" a R$ %s, abaixo do preço mínimo de R$ %s.',
                    $produto->descricao ?? ('produto '.$produto->id),
                    number_format($unitarioFinal, 2, ',', '.'),
                    number_format($minimo, 2, ',', '.'),
                ));
            }
        }

        // 2) Teto da pessoa — este sim a Central pode liberar. Desconto já
        // aprovado não é reavaliado: a aprovação É a autorização, e reavaliar
        // derrubaria o que o supervisor acabou de conceder.
        if (($contexto['desconto_aprovado'] ?? false) === true) {
            return;
        }

        $usuario = $contexto['usuario'] ?? null;

        $r = $this->alcada->tetoDoItem($usuario, [
            'produto_id' => $produto->id,
            'quantidade' => $qtd,
            'preco_unitario' => $preco,
            'setor_id' => $contexto['setor_id'] ?? ($item['setor_id'] ?? null),
            'condicaopagamento_id' => $contexto['condicaopagamento_id'] ?? ($item['condicaopagamento_id'] ?? null),
        ], (float) $produto->preco_venda);

        if ($desc <= $r['teto'] + 0.001) {   // tolerância de arredondamento
            return;
        }

        $sugestao = $r['permite_solicitar']
            ? ' Para conceder mais, envie a solicitação à central de vendas.'
            : '';

        throw new \DomainException(sprintf(
            'Desconto de R$ %s em "%s" passa da sua alçada (máximo R$ %s).%s',
            number_format($desc, 2, ',', '.'),
            $produto->descricao ?? ('produto '.$produto->id),
            number_format($r['teto'], 2, ',', '.'),
            $sugestao,
        ));
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
