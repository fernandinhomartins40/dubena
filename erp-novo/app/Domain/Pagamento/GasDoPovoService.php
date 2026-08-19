<?php

namespace App\Domain\Pagamento;

use App\Models\Cliente\Cliente;
use App\Models\EmpresaConfig;
use App\Models\Financeiro\CondicaoPagamento;
use App\Models\Pedido\Pedido;
use App\Models\Produto\Produto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Programa Gás do Povo — como o legado realmente opera.
 *
 * **Não é um módulo com saldo e saque.** A auditoria do `ctrl-web`
 * (`docs/02-auditoria-legado/GAS_DO_POVO_NO_LEGADO.md`) mostrou que lá não existe
 * tela, rota nem controller do programa: ele é um MODO DE VENDA, definido por
 * três coisas que já existem no cadastro —
 *
 *  1. **parâmetros na config da empresa** (produto único, condição de pagamento
 *     que marca a venda, frete fixo e a conta contábil da entrega);
 *  2. **um checkbox no cliente** dizendo que ele é beneficiário;
 *  3. **um preço próprio no produto** (`preco_gasdopovo`).
 *
 * A venda vira "do programa" quando as DUAS pontas coincidem — cliente
 * beneficiário E condição de pagamento do programa (`PedidoUtil.php:337` do
 * legado). Nenhuma sozinha basta, e depois de criado o pedido a condição é
 * travada nos dois sentidos: mudá-la alteraria o preço subsidiado e a prestação
 * de contas.
 *
 * Este serviço é de LEITURA: monta o painel de conferência do programa. Quem
 * marca o pedido é a criação da venda, não esta classe.
 */
final class GasDoPovoService
{
    /**
     * Os parâmetros do programa, resolvidos a partir da config da empresa.
     *
     * Vêm de `empresa_configs.dados` — onde o ETL os gravou com o nome do
     * legado. Cada um é devolvido com o RÓTULO junto, porque um id solto na tela
     * não diz nada a quem confere.
     *
     * @return array<string, mixed>
     */
    public function parametros(int $empresaId): array
    {
        $dados = EmpresaConfig::query()->where('empresa_id', $empresaId)->value('dados') ?? [];

        $produtoId = $this->inteiroOuNull($dados['gp_produto_id'] ?? null);
        $condicaoId = $this->inteiroOuNull($dados['gp_condicaopagamento_id'] ?? null);
        $condicaoFreteId = $this->inteiroOuNull($dados['gp_condicaopagamento_frete_id'] ?? null);

        $produto = $produtoId !== null ? Produto::query()->find($produtoId) : null;

        return [
            'configurado' => $produtoId !== null && $condicaoId !== null,
            'produto_id' => $produtoId,
            'produto' => $produto?->descricao,
            // O preço do programa mora no PRODUTO, não na config: é o subsídio.
            'preco' => $produto?->preco_gasdopovo !== null ? (float) $produto->preco_gasdopovo : null,
            'preco_venda' => $produto?->preco_venda !== null ? (float) $produto->preco_venda : null,
            'condicaopagamento_id' => $condicaoId,
            'condicaopagamento' => $this->descricaoCondicao($condicaoId),
            'condicaopagamento_frete_id' => $condicaoFreteId,
            'condicaopagamento_frete' => $this->descricaoCondicao($condicaoFreteId),
            'valor_frete' => isset($dados['valorfretegp']) ? (float) $dados['valorfretegp'] : null,
            'ccfrete_id' => $this->inteiroOuNull($dados['ccfretegp_id'] ?? null),
            'pcfrete_id' => $this->inteiroOuNull($dados['pcfretegp_id'] ?? null),
        ];
    }

    /**
     * Resumo do programa no período — é o que se presta conta.
     *
     * O desconto por botijão sai da diferença entre o preço normal e o do
     * programa: é o subsídio efetivamente concedido, e o número que a
     * distribuidora cobra.
     *
     * @return array<string, mixed>
     */
    public function resumo(int $empresaId, ?string $de = null, ?string $ate = null): array
    {
        $parametros = $this->parametros($empresaId);

        $query = Pedido::query()->where('gasdopovo', true);
        // `whereDate` e não `whereBetween`: em coluna datetime a comparação de
        // string perde o último dia do intervalo.
        if ($de !== null) {
            $query->whereDate('datahora', '>=', $de);
        }
        if ($ate !== null) {
            $query->whereDate('datahora', '<=', $ate);
        }

        $pedidos = (clone $query)->count();
        $valor = (float) (clone $query)->sum('valor_venda');

        $botijoes = (float) (clone $query)
            ->join('pedidoitens', 'pedidoitens.pedido_id', '=', 'pedidos.id')
            ->when(
                $parametros['produto_id'] !== null,
                fn ($b) => $b->where('pedidoitens.produto_id', $parametros['produto_id']),
            )
            ->sum('pedidoitens.quantidade');

        $desconto = null;
        if ($parametros['preco'] !== null && $parametros['preco_venda'] !== null) {
            $desconto = round(($parametros['preco_venda'] - $parametros['preco']) * $botijoes, 2);
        }

        return [
            'pedidos' => $pedidos,
            'valor' => round($valor, 2),
            'botijoes' => $botijoes,
            'ticket_medio' => $pedidos > 0 ? round($valor / $pedidos, 2) : 0.0,
            // Subsídio concedido no período (preço normal − preço do programa).
            // Null quando falta um dos preços: melhor não informar do que informar errado.
            'subsidio' => $desconto,
            'beneficiarios' => Cliente::query()->where('gasdopovo', true)->count(),
        ];
    }

    /**
     * Vendas do programa, mês a mês — a série que mostra a evolução.
     *
     * @return list<array<string, mixed>>
     */
    public function porMes(int $empresaId, int $meses = 12): array
    {
        $desde = Carbon::now()->startOfMonth()->subMonths($meses - 1);

        // `to_char` é do Postgres; os testes rodam em sqlite. A expressão do mês
        // é a única parte que varia — o resto da consulta é a mesma.
        $mes = DB::connection()->getDriverName() === 'pgsql'
            ? "to_char(datahora, 'YYYY-MM')"
            : "strftime('%Y-%m', datahora)";

        return Pedido::query()
            ->where('gasdopovo', true)
            ->where('datahora', '>=', $desde)
            ->selectRaw("{$mes} AS mes, count(*) AS pedidos, sum(valor_venda) AS valor")
            ->groupBy(DB::raw($mes))
            ->orderBy(DB::raw($mes))
            ->get()
            ->map(fn ($l) => [
                'mes' => $l->mes,
                'pedidos' => (int) $l->pedidos,
                'valor' => round((float) $l->valor, 2),
            ])
            ->all();
    }

    private function descricaoCondicao(?int $id): ?string
    {
        return $id !== null
            ? CondicaoPagamento::query()->find($id)?->descricao
            : null;
    }

    private function inteiroOuNull(mixed $v): ?int
    {
        return ($v === null || $v === '') ? null : (int) $v;
    }
}
