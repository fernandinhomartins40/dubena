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
 */
final class BalanceInvariant implements Invariant
{
    /**
     * @param list<string> $chave colunas que identificam a "conta" (ex.: [setor_id, produto_id])
     */
    public function __construct(
        private MigrationContext $ctx,
        private string $tabelaMovimentos,
        private string $colunaMovimento,
        private string $tabelaSaldo,
        private string $colunaSaldo,
        private array $chave,
        private float $tolerancia = 0.001,
    ) {
    }

    public function nome(): string
    {
        return "saldo Σ{$this->tabelaMovimentos}.{$this->colunaMovimento} = {$this->tabelaSaldo}.{$this->colunaSaldo}";
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

        $divergencias = 0;
        $exemplo = null;

        foreach ($somas as $linha) {
            $saldoQuery = $novo->table($this->tabelaSaldo);
            foreach ($this->chave as $col) {
                $saldoQuery->where($col, $linha->{$col});
            }
            $saldo = (float) ($saldoQuery->value($this->colunaSaldo) ?? 0);
            $derivado = (float) $linha->total;

            if (abs($saldo - $derivado) > $this->tolerancia) {
                $divergencias++;
                $exemplo ??= "chave(".implode(',', array_map(fn ($c) => $linha->{$c}, $this->chave)).") derivado={$derivado} saldo={$saldo}";
            }
        }

        return $divergencias === 0
            ? InvariantResult::ok($this->nome(), 'Σ movimentos = saldo em todas as chaves')
            : InvariantResult::falha($this->nome(), "saldo divergente em {$divergencias} chave(s): {$exemplo}", 0, $divergencias);
    }
}
