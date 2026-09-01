<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Invariant;
use App\Etl\Support\InvariantResult;
use App\Models\Estado;

/**
 * Invariante de contagem contra o conjunto-semente (quando o banco legado não
 * está disponível neste ambiente). Em produção/cutover, EstadosMigrator usa a
 * CountInvariant contra o legado real.
 */
final class SementeCountInvariant implements Invariant
{
    public function __construct(private int $esperado) {}

    public function nome(): string
    {
        return 'contagem estados (semente)';
    }

    public function verificar(): InvariantResult
    {
        $obtido = Estado::count();

        return $obtido === $this->esperado
            ? InvariantResult::ok($this->nome(), "estados={$obtido}")
            : InvariantResult::falha($this->nome(), 'contagem divergente', $this->esperado, $obtido);
    }
}
