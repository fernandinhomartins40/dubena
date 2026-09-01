<?php

namespace App\Etl\Invariants;

use App\Etl\Contracts\Invariant;
use App\Etl\Support\InvariantResult;
use App\Etl\Support\MigrationContext;

/**
 * Invariante de INTEGRIDADE (check #3): zero FK órfã no banco NOVO.
 * Verifica que toda linha de $tabela.$coluna referencia uma linha existente em
 * $tabelaRef.$colunaRef (ignorando nulos).
 */
final class IntegrityInvariant implements Invariant
{
    public function __construct(
        private MigrationContext $ctx,
        private string $tabela,
        private string $coluna,
        private string $tabelaRef,
        private string $colunaRef = 'id',
    ) {}

    public function nome(): string
    {
        return "integridade {$this->tabela}.{$this->coluna}→{$this->tabelaRef}.{$this->colunaRef}";
    }

    public function verificar(): InvariantResult
    {
        $orfas = (int) $this->ctx->novo()->table($this->tabela)
            ->whereNotNull($this->coluna)
            ->whereNotExists(function ($q) {
                $q->select($this->ctx->novo()->raw(1))
                    ->from($this->tabelaRef)
                    ->whereColumn("{$this->tabelaRef}.{$this->colunaRef}", "{$this->tabela}.{$this->coluna}");
            })
            ->count();

        return $orfas === 0
            ? InvariantResult::ok($this->nome(), 'sem FK órfã')
            : InvariantResult::falha($this->nome(), 'FK órfã encontrada', 0, $orfas);
    }
}
