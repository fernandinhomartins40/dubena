<?php

namespace App\Domain\Mobile;

use App\Models\Produto\Produto;
use App\Models\Produto\ProdutoCondicaoPreco;
use Illuminate\Validation\ValidationException;

/**
 * CotacaoMobileService (F3) — AUTORIDADE de preço do app.
 *
 * Substitui o cálculo que vivia no cliente (flashStore.calculateTotal/applyDiscount):
 * o app envia apenas itens (produto_id + quantidade), a condição, o cupom e o flag
 * Gás do Povo; o SERVIDOR resolve preço unitário, subtotal, desconto e total. O app
 * só EXIBE o que o servidor calcula — fim da fraude de valor (achado crítico da auditoria).
 *
 * Os mesmos números são reaplicados na criação do pedido (PedidoMobileService),
 * garantindo que o valor exibido no checkout é o valor cobrado.
 */
class CotacaoMobileService
{
    public function __construct(private CatalogoMobileService $catalogo) {}

    /**
     * Calcula a cotação do carrinho.
     *
     * @param  array{itens: list<array{produto_id:int, quantidade:float}>, condicao_id?:?int, codigo_cupom?:?string, gasdopovo?:bool}  $payload
     * @return array{
     *   itens: list<array<string,mixed>>,
     *   subtotal: float, desconto: float, total: float,
     *   cupom: ?array<string,mixed>, indisponiveis: list<int>
     * }
     */
    public function cotar(int $empresaId, int $grupoId, array $payload): array
    {
        $gasdopovo = (bool) ($payload['gasdopovo'] ?? false);
        $condicaoId = isset($payload['condicao_id']) ? (int) $payload['condicao_id'] : null;
        $linhas = $payload['itens'] ?? [];
        if (empty($linhas)) {
            throw ValidationException::withMessages(['itens' => 'Carrinho vazio.']);
        }

        // Carrega os produtos pedidos de uma vez (escopados pela empresa do token).
        $ids = array_values(array_unique(array_map(fn ($i) => (int) $i['produto_id'], $linhas)));
        $produtos = Produto::query()
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->whereIn('id', $ids)
            ->get(['id', 'descricao', 'preco_venda', 'preco_gasdopovo'])
            ->keyBy('id');

        // Preços por condição de pagamento (F3c). Mapa [produto_id => valor] para a
        // condição/gp selecionada; vazio se nenhuma condição foi informada.
        $precosPorCondicao = $this->precosPorCondicao($empresaId, $ids, $condicaoId, $gasdopovo);

        $itens = [];
        $indisponiveis = [];
        $subtotal = 0.0;

        foreach ($linhas as $linha) {
            $pid = (int) $linha['produto_id'];
            $qtd = (float) $linha['quantidade'];
            $produto = $produtos->get($pid);

            // Preço da condição (se houver) tem prioridade sobre o preço base do produto.
            $precoUnit = $precosPorCondicao[$pid] ?? $this->precoUnitario($produto, $gasdopovo);
            if ($produto === null || $precoUnit === null || $qtd <= 0) {
                $indisponiveis[] = $pid;

                continue;
            }

            $totalItem = round($precoUnit * $qtd, 2);
            $subtotal += $totalItem;

            $itens[] = [
                'produto_id' => $pid,
                'descricao' => $produto->descricao,
                'quantidade' => $qtd,
                'preco_unitario' => $precoUnit,
                'total' => $totalItem,
            ];
        }

        if (empty($itens)) {
            throw ValidationException::withMessages(['itens' => 'Nenhum item disponível no carrinho.']);
        }

        $subtotal = round($subtotal, 2);

        // Cupom (percentual) aplicado sobre o subtotal, server-side.
        $desconto = 0.0;
        $cupomInfo = null;
        if (! empty($payload['codigo_cupom'])) {
            $promo = $this->catalogo->validarCupom($grupoId, (string) $payload['codigo_cupom']);
            $pct = (float) ($promo->desconto_percentual ?? 0);
            $desconto = round($subtotal * $pct / 100, 2);
            $cupomInfo = [
                'codigo' => $promo->codigo,
                'descricao' => $promo->descricao,
                'desconto_percentual' => $pct,
            ];
        }

        $total = round(max(0, $subtotal - $desconto), 2);

        return [
            'itens' => $itens,
            'subtotal' => $subtotal,
            'desconto' => $desconto,
            'total' => $total,
            'cupom' => $cupomInfo,
            'indisponiveis' => array_values(array_unique($indisponiveis)),
        ];
    }

    /**
     * Preços por condição de pagamento (F3c). Retorna [produto_id => valor] para a
     * condição/gp selecionada. Vazio quando não há condição informada — aí a cotação
     * usa o preço base do produto (compatível com quem não cadastrou preço por condição).
     *
     * @param  list<int>  $produtoIds
     * @return array<int,float>
     */
    private function precosPorCondicao(int $empresaId, array $produtoIds, ?int $condicaoId, bool $gasdopovo): array
    {
        if ($condicaoId === null || empty($produtoIds)) {
            return [];
        }

        return ProdutoCondicaoPreco::query()
            ->where('empresa_id', $empresaId)
            ->where('condicaopagamento_id', $condicaoId)
            ->where('gasdopovo', $gasdopovo)
            ->whereIn('produto_id', $produtoIds)
            ->get(['produto_id', 'valor'])
            ->mapWithKeys(fn ($p) => [(int) $p->produto_id => (float) $p->valor])
            ->all();
    }

    /** Preço unitário aplicável: Gás do Povo (se pedido e disponível) ou preço de venda. */
    private function precoUnitario(?Produto $produto, bool $gasdopovo): ?float
    {
        if ($produto === null) {
            return null;
        }

        if ($gasdopovo) {
            return $produto->preco_gasdopovo !== null ? (float) $produto->preco_gasdopovo : null;
        }

        return (float) $produto->preco_venda;
    }
}
