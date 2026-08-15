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
        // Conexão do legado indisponível (dev/CI sem dump): não há o que
        // comparar — a invariante não se aplica.
        //
        // MAS: conexão disponível com a TABELA ausente é FALHA, não skip.
        // Foi exatamente assim que módulos inteiros migraram "0 linhas com
        // sucesso" sem ninguém ver (auditoria 2026-08-14): o nome errado ou o
        // espelho incompleto silenciava a própria checagem que o detectaria.
        try {
            $temTabela = $this->ctx->legado()->getSchemaBuilder()->hasTable($this->tabelaLegado);
        } catch (\Throwable) {
            return InvariantResult::ok(
                $this->nome(),
                'legado indisponível — não se aplica'
            );
        }

        if (! $temTabela) {
            return InvariantResult::falha(
                $this->nome(),
                "origem `{$this->tabelaLegado}` NÃO existe no legado — nome errado "
                    .'ou tabela fora do MAPA do espelho (espelhar_oracle.py)',
                -1,
                (int) $this->ctx->novo()->table($this->tabelaNova)->count(),
            );
        }

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
