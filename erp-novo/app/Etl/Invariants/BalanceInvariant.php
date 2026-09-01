<?php

namespace App\Etl\Invariants;

use App\Etl\Contracts\Invariant;
use App\Etl\Support\InvariantResult;
use App\Etl\Support\MigrationContext;

/**
 * Invariante de SALDO DERIVÁVEL (a chave do plano, princípio #5):
 *   Σ movimentos (por chave) == saldo materializado.
 *
 * Genérica e reutilizável (estoque N3: Σ estoquehistorico = estoquesaldos;
 * caixa N6: Σ contamovimentos = conta.saldoatual). Opera no banco NOVO — garante
 * que a materialização do saldo nunca divergiu do log de movimentos.
 *
 * **T5.1 — o escopo que a realidade impôs.** Esta classe existia com teste
 * unitário e NENHUM migrator a registrava; ao registrá-la, ela reprovou 28 de
 * 28 contas e 121 chaves de estoque. A investigação mostrou que **o legado
 * nunca manteve essa igualdade**: a conta 692 tem `saldoatual = 0` na ORIGEM
 * com R$ 26,5 milhões em movimentos. O ETL copiou fielmente — o desvio é do
 * sistema antigo, que zerava o saldo periodicamente sem lançar contrapartida.
 *
 * Por isso `$idMinimoSaldo`: a invariante só cobra as chaves cujo saldo NASCEU
 * no sistema novo (id acima da faixa preservada do legado). Comparar Σ parcial
 * contra saldo total seria pior que não verificar — daria falha em tudo e
 * ensinaria a ignorar o portão. Com o recorte, ela guarda o que pode guardar:
 * que a materialização feita PELO SISTEMA NOVO nunca diverge do log.
 *
 * O histórico herdado fica coberto por `CountInvariant`/`SumInvariant` (as
 * linhas e os totais vieram), e a divergência do legado está registrada em
 * `docs/gauntlet/T5.1_ACHADOS.md` como risco conhecido, não como bug novo.
 */
final class BalanceInvariant implements Invariant
{
    /**
     * @param  list<string>  $chave  colunas que identificam a "conta" na tabela de
     *                               MOVIMENTOS (ex.: [setor_id, produto_id])
     * @param  array<string,string>  $chaveNoSaldo  mapeia coluna do movimento =>
     *                                              coluna correspondente na tabela de saldo, quando os nomes diferem.
     *                                              Caso do caixa: `contamovimentos.conta_id` casa com `contas.id` —
     *                                              sem este mapa a invariante procurava `contas.conta_id` e quebrava,
     *                                              que é uma das razões de ela nunca ter sido usada em produção.
     */
    public function __construct(
        private MigrationContext $ctx,
        private string $tabelaMovimentos,
        private string $colunaMovimento,
        private string $tabelaSaldo,
        private string $colunaSaldo,
        private array $chave,
        private float $tolerancia = 0.001,
        private array $chaveNoSaldo = [],
        private ?int $idMinimoSaldo = null,
        private string $colunaIdSaldo = 'id',
    ) {}

    public function nome(): string
    {
        $recorte = $this->idMinimoSaldo !== null ? " (chaves novas, {$this->colunaIdSaldo}>={$this->idMinimoSaldo})" : '';

        return "saldo Σ{$this->tabelaMovimentos}.{$this->colunaMovimento} = {$this->tabelaSaldo}.{$this->colunaSaldo}{$recorte}";
    }

    public function verificar(): InvariantResult
    {
        $novo = $this->ctx->novo();

        // Σ movimentos por chave.
        $somas = $novo->table($this->tabelaMovimentos)
            ->select($this->chave)
            ->selectRaw("sum({$this->colunaMovimento}) as total")
            ->groupBy($this->chave)
            ->get();

        if ($somas->isEmpty()) {
            // F7-10 — recorte vazio é INCONCLUSIVO, não aprovação.
            //
            // Antes devolvia `ok`, com o raciocínio de não gritar num banco
            // recém-criado. O problema é que a mesma resposta serve para dois
            // fatos opostos: antes da carga, "sem movimentos" é o esperado;
            // DEPOIS dela, significa que a carga não trouxe nada — e o portão
            // aprovava assim mesmo.
            return InvariantResult::inconclusiva($this->nome(), 'sem movimentos no recorte: nada a verificar');
        }

        $divergencias = 0;
        $exemplo = null;

        $verificadas = 0;

        foreach ($somas as $linha) {
            $saldoQuery = $novo->table($this->tabelaSaldo);
            foreach ($this->chave as $col) {
                $saldoQuery->where($this->chaveNoSaldo[$col] ?? $col, $linha->{$col});
            }

            if ($this->idMinimoSaldo !== null) {
                // Só as chaves nascidas no sistema novo respondem pelo saldo.
                $saldoQuery->where($this->colunaIdSaldo, '>=', $this->idMinimoSaldo);
            }

            $registro = $saldoQuery->first([$this->colunaSaldo]);

            if ($registro === null) {
                continue;   // chave fora do recorte
            }

            $verificadas++;
            $saldo = (float) ($registro->{$this->colunaSaldo} ?? 0);
            $derivado = (float) $linha->total;

            if (abs($saldo - $derivado) > $this->tolerancia) {
                $divergencias++;
                $exemplo ??= 'chave('.implode(',', array_map(fn ($c) => $linha->{$c}, $this->chave)).") derivado={$derivado} saldo={$saldo}";
            }
        }

        return $divergencias === 0
            ? InvariantResult::ok($this->nome(), "Σ movimentos = saldo em {$verificadas} chave(s)")
            : InvariantResult::falha($this->nome(), "saldo divergente em {$divergencias} chave(s): {$exemplo}", 0, $divergencias);
    }
}
