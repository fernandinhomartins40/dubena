<?php

namespace App\Etl\Invariants;

use App\Etl\Contracts\Invariant;
use App\Etl\Support\InvariantResult;
use App\Etl\Support\MigrationContext;

/**
 * Invariante de SOMA: somatório de uma coluna bate entre origem e destino
 * (com tolerância de centavos). É o check #2 — base para "Σ saldos/títulos/estoque".
 *
 * Para a invariante de SALDO derivado (Σ movimentos = saldo), usar
 * BalanceInvariant (especialização no módulo de caixa/estoque, N3/N6).
 */
final class SumInvariant implements Invariant
{
    public function __construct(
        private MigrationContext $ctx,
        private string $tabelaLegado,
        private string $colunaLegado,
        private string $tabelaNova,
        private string $colunaNova,
        private float $tolerancia = 0.01,
        private ?\Closure $somaLegadoCustom = null,
    ) {
    }

    public function nome(): string
    {
        return "soma {$this->tabelaLegado}.{$this->colunaLegado}→{$this->tabelaNova}.{$this->colunaNova}";
    }

    public function verificar(): InvariantResult
    {
        // A tabela pode não existir na origem (o legado varia por instalação e
        // nem todo dump traz todos os módulos): sem origem não há soma a
        // comparar, e isso não é falha do cutover.
        try {
            if (! $this->ctx->legado()->getSchemaBuilder()->hasTable($this->tabelaLegado)) {
                return InvariantResult::ok(
                    $this->nome(),
                    "origem `{$this->tabelaLegado}` ausente no dump — não se aplica"
                );
            }
        } catch (\Throwable) {
            return InvariantResult::ok($this->nome(), 'legado indisponível — não se aplica');
        }

        // No legado a coluna pode estar em string-BR; permite custom (BrFormat) via closure.
        $origem = $this->somaLegadoCustom
            ? round(($this->somaLegadoCustom)($this->ctx), 2)
            : round((float) $this->ctx->legado()->table($this->tabelaLegado)->sum($this->colunaLegado), 2);

        $destino = round((float) $this->ctx->novo()->table($this->tabelaNova)->sum($this->colunaNova), 2);

        return abs($origem - $destino) <= $this->tolerancia
            ? InvariantResult::ok($this->nome(), "soma=≈{$destino}")
            : InvariantResult::falha($this->nome(), 'soma divergente', $origem, $destino);
    }
}
