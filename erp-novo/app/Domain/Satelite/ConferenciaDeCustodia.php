<?php

namespace App\Domain\Satelite;

use App\Models\Satelite\Comodato;
use App\Models\Satelite\ComodatoMovimento;
use Illuminate\Support\Collection;

/**
 * F4-04 — o saldo de custódia passa a ter prova.
 *
 * O ledger patrimonial já existe (`comodato_movimentos`: tipo, quantidade,
 * `saldo_apos`, estorno explícito e ator), e o `sentido` já separa o comodato
 * concedido do recebido — `fornecedor` não decide direção.
 *
 * O que faltava é o mesmo que faltava no estoque (F4-02): **conferir a projeção
 * contra o ledger**.
 *
 * `comodatos.quantidade` e `quantidade_devolvida` são a projeção; ninguém
 * somava os movimentos para verificar se batem. E aqui o que está em jogo é
 * patrimônio em poder de terceiro: um saldo de custódia errado significa
 * vasilhame que a revenda acha que tem — ou que acha que emprestou e não
 * emprestou.
 *
 * ## Não ajusta, pela mesma razão
 *
 * Se a projeção diz que o cliente tem 5 e o ledger soma 3, ou a projeção está
 * errada, ou **faltam movimentos** — vasilhames que saíram sem registro.
 * Sobrescrever resolve a tela e apaga a pergunta.
 */
class ConferenciaDeCustodia
{
    /**
     * Contratos cuja projeção não bate com a soma dos movimentos.
     *
     * @return Collection<int, array{comodato_id:int, cliente_id:int, produto_id:int, projetado:float, ledger:float, diferenca:float}>
     */
    public function divergencias(int $empresaId): Collection
    {
        // `EMPRESTIMO` soma ao que está em poder do cliente; `DEVOLUCAO`
        // subtrai. `ESTORNO` desfaz um movimento anterior e por isso carrega o
        // sinal do que estorna — a soma abaixo o trata pelo `estorna_id`.
        $movimentos = ComodatoMovimento::withoutTenant()
            ->where('empresa_id', $empresaId)
            // `id` e obrigatorio aqui: sem ele o par estornado/estorno nao se
            // reconhece, e a soma conta o movimento desfeito.
            ->get(['id', 'comodato_id', 'tipo', 'quantidade', 'estorna_id'])
            ->groupBy('comodato_id');

        return Comodato::withoutTenant()
            ->where('empresa_id', $empresaId)
            ->get(['id', 'cliente_id', 'produto_id', 'quantidade', 'quantidade_devolvida'])
            ->map(function (Comodato $c) use ($movimentos) {
                $projetado = round(
                    (float) $c->quantidade - (float) $c->quantidade_devolvida,
                    3,
                );

                $ledger = $this->somar($movimentos->get($c->id) ?? collect());

                return [
                    'comodato_id' => (int) $c->id,
                    'cliente_id' => (int) $c->cliente_id,
                    'produto_id' => (int) $c->produto_id,
                    'projetado' => $projetado,
                    'ledger' => $ledger,
                    'diferenca' => round($projetado - $ledger, 3),
                ];
            })
            // Mesma tolerância do estoque: 3 casas no banco, e igualdade exata
            // de float viraria ruído de arredondamento.
            ->filter(fn (array $l) => abs($l['diferenca']) >= 0.001)
            ->sortByDesc(fn (array $l) => abs($l['diferenca']))
            ->values();
    }

    /**
     * Soma assinada dos movimentos de um contrato.
     *
     * @param  Collection<int, ComodatoMovimento>  $movimentos
     */
    private function somar(Collection $movimentos): float
    {
        // Um movimento estornado e o seu estorno se anulam: somar os dois com o
        // mesmo sinal contaria o empréstimo duas vezes.
        $estornados = $movimentos->pluck('estorna_id')->filter()->flip();

        $total = $movimentos->reduce(function (float $soma, ComodatoMovimento $m) use ($estornados) {
            if ($estornados->has($m->id) || $m->estorna_id !== null) {
                return $soma;
            }

            return $m->tipo === ComodatoMovimento::DEVOLUCAO
                ? $soma - (float) $m->quantidade
                : $soma + (float) $m->quantidade;
        }, 0.0);

        return round($total, 3);
    }

    /** A custódia fecha com o ledger? */
    public function fecha(int $empresaId): bool
    {
        return $this->divergencias($empresaId)->isEmpty();
    }
}
