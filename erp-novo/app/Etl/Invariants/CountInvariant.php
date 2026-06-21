<?php

namespace App\Etl\Invariants;

use App\Etl\Contracts\Invariant;
use App\Etl\Support\InvariantResult;
use App\Etl\Support\MigrationContext;

/**
 * Invariante de CONTAGEM: nº de registros no legado == nº no novo (menos descartes).
 * É o check #1 da regra de ouro do plano.
 */
final class CountInvariant implements Invariant
{
    public function __construct(
        private MigrationContext $ctx,
        private string $tabelaLegado,
        private string $tabelaNova,
        private int $descartesEsperados = 0,
        private ?string $whereLegado = null,
    ) {
    }

    public function nome(): string
    {
        return "contagem {$this->tabelaLegado}→{$this->tabelaNova}";
    }

    public function verificar(): InvariantResult
    {
        $qLegado = $this->ctx->legado()->table($this->tabelaLegado);
        if ($this->whereLegado) {
            $qLegado->whereRaw($this->whereLegado);
        }
        $origem = (int) $qLegado->count() - $this->descartesEsperados;
        $destino = (int) $this->ctx->novo()->table($this->tabelaNova)->count();

        return $origem === $destino
            ? InvariantResult::ok($this->nome(), "origem=destino={$destino}")
            : InvariantResult::falha($this->nome(), 'contagem divergente', $origem, $destino);
    }
}
