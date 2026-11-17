<?php

namespace App\Domain\Pedido;

use App\Models\EmpresaConfig;
use App\Models\Produto\Produto;

/**
 * Quantidade plausível de um item de pedido (P2-D-12).
 *
 * Medido na Rodada 2: a API criou o pedido #502 com 1,5 botijão e o #503 com
 * 1.000.000 unidades; só ao concluir o estoque barrou o segundo. Duas regras,
 * de naturezas diferentes:
 *
 *  - **Inteiro quando a unidade é "UN".** Não é convenção de revenda: é o que
 *    a unidade de medida diz (a mesma sigla vai no `uCom` da NF-e). Produto em
 *    kg, litro ou metro continua aceitando fração.
 *  - **Teto por item é CONFIGURAÇÃO do tenant** (`empresa_configs.dados
 *    ['pedido_quantidade_maxima_item']`). Sem valor, não há teto: o número
 *    certo depende de quem a revenda atende (doméstico × industrial), e
 *    inventar um padrão aqui seria transformar o hábito de uma revenda em
 *    regra do produto.
 */
final class QuantidadeDoItem
{
    /** Siglas de unidade contável — não existe meio botijão. */
    private const UNIDADES_INTEIRAS = ['UN', 'UND', 'UNID', 'UNIDADE', 'PC', 'PÇ', 'CX', 'BJ'];

    public const CHAVE_TETO = 'pedido_quantidade_maxima_item';

    /** Mensagem do problema, ou null se a quantidade é aceitável. */
    public static function problema(Produto $produto, float $quantidade, ?float $teto): ?string
    {
        $sigla = mb_strtoupper(trim((string) $produto->unidade?->sigla), 'UTF-8');

        if (in_array($sigla, self::UNIDADES_INTEIRAS, true) && floor($quantidade) != $quantidade) {
            return "\"{$produto->descricao}\" é vendido por unidade: informe uma quantidade inteira.";
        }

        if ($teto !== null && $quantidade > $teto) {
            $limite = rtrim(rtrim(number_format($teto, 3, ',', '.'), '0'), ',');

            return "Quantidade acima do máximo por item configurado para a revenda ({$limite}). Confira o número digitado.";
        }

        return null;
    }

    /** Teto configurado pela revenda para a empresa, ou null (sem teto). */
    public static function teto(?int $empresaId): ?float
    {
        if (! $empresaId) {
            return null;
        }

        $valor = EmpresaConfig::query()->where('empresa_id', $empresaId)->first()?->dados[self::CHAVE_TETO] ?? null;

        return is_numeric($valor) && (float) $valor > 0 ? (float) $valor : null;
    }
}
