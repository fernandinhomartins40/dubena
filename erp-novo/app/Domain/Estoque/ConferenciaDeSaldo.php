<?php

namespace App\Domain\Estoque;

use App\Models\Estoque\EstoqueHistorico;
use App\Models\Estoque\EstoqueSaldo;
use Illuminate\Support\Collection;

/**
 * F4-02 — a projeção de saldo é recalculável, e a divergência é auditável.
 *
 * `estoquesaldos` é a projeção: mantida na mesma transação do movimento, com
 * lock pessimista. Isso está certo e é o que dá o saldo em O(1) na tela.
 *
 * O que faltava é a outra metade: **provar que ela bate com o ledger**.
 *
 * Uma projeção sem conferência é uma afirmação sem prova. Ela pode divergir por
 * um bug corrigido meses atrás, por um `UPDATE` manual em produção, por uma
 * migração de dados — e ninguém descobre, porque a tela mostra a projeção e
 * ninguém soma o ledger à mão.
 *
 * ## O que este serviço NÃO faz
 *
 * **Não ajusta.** O gate da F4 é explícito: *"divergência nunca é ajustada
 * silenciosamente"*.
 *
 * A razão é que o ajuste automático destrói a evidência. Se a projeção diz 10 e
 * o ledger soma 8, há duas hipóteses — a projeção está errada (bug), ou falta um
 * movimento no ledger (mercadoria que entrou sem registro). Sobrescrever a
 * projeção com 8 resolve a tela e apaga a pergunta; e se a resposta era a
 * segunda, a mercadoria some da contabilidade sem deixar rastro.
 *
 * Quem decide é gente, com o relatório na mão.
 */
class ConferenciaDeSaldo
{
    /**
     * Compara a projeção com a soma do ledger, por (setor, produto).
     *
     * @return Collection<int, array{setor_id:int, produto_id:int, projetado:float, ledger:float, diferenca:float}>
     */
    public function divergencias(int $empresaId): Collection
    {
        $doLedger = EstoqueHistorico::withoutTenant()
            ->where('empresa_id', $empresaId)
            ->selectRaw('setor_id, produto_id, SUM(quantidade) AS total')
            ->groupBy('setor_id', 'produto_id')
            ->get()
            ->keyBy(fn ($l) => $l->setor_id.':'.$l->produto_id);

        $projetados = EstoqueSaldo::withoutTenant()
            ->where('empresa_id', $empresaId)
            ->get(['setor_id', 'produto_id', 'quantidade'])
            ->keyBy(fn ($s) => $s->setor_id.':'.$s->produto_id);

        // A união das duas chaves, e não só as da projeção: um par que existe no
        // ledger sem linha de saldo é justamente o caso mais grave — mercadoria
        // movimentada que não aparece em lugar nenhum.
        $chaves = $projetados->keys()->merge($doLedger->keys())->unique();

        return $chaves
            ->map(function (string $chave) use ($projetados, $doLedger) {
                [$setorId, $produtoId] = array_map('intval', explode(':', $chave));

                $projetado = (float) ($projetados[$chave]->quantidade ?? 0);
                $ledger = (float) ($doLedger[$chave]->total ?? 0);

                return [
                    'setor_id' => $setorId,
                    'produto_id' => $produtoId,
                    'projetado' => $projetado,
                    'ledger' => $ledger,
                    'diferenca' => round($projetado - $ledger, 3),
                ];
            })
            // Tolerância de 0,001: a quantidade tem 3 casas no banco, e comparar
            // float por igualdade exata produziria divergência de arredondamento
            // que não é divergência de estoque.
            ->filter(fn (array $l) => abs($l['diferenca']) >= 0.001)
            ->sortByDesc(fn (array $l) => abs($l['diferenca']))
            ->values();
    }

    /**
     * A projeção fecha com o ledger?
     *
     * É a pergunta do gate da F4 ("a soma do ledger fecha por tenant / empresa /
     * local / item"), respondível numa linha.
     */
    public function fecha(int $empresaId): bool
    {
        return $this->divergencias($empresaId)->isEmpty();
    }
}
