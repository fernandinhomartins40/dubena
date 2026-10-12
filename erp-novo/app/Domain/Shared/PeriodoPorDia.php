<?php

namespace App\Domain\Shared;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Carbon;

/**
 * Filtro "do dia X ao dia Y" em coluna de DATA E HORA, que usa índice.
 *
 * `whereDate()` entrou nesta base para não perder o último dia do período (ver
 * SemanticaTemporalTest): `whereBetween` com 'AAAA-MM-DD' corta às 00:00 do
 * último dia. Mas no Postgres ele vira `coluna::date = ?`, e a conversão
 * impede o índice: "pedidos de hoje" no painel varria os 400 mil pedidos e
 * rodava a checagem de RLS em cada um — 3,1 s medidos na homologação, para
 * devolver zero linhas.
 *
 * O intervalo semiaberto resolve os dois: `>= início 00:00` e `< dia seguinte
 * ao fim 00:00` inclui o último dia inteiro (até 23:59:59) e é uma faixa que o
 * índice percorre direto.
 *
 * Só para coluna `timestamp`. Em coluna `date` o `whereDate` já usa índice
 * (o cast é nulo) e pode ficar.
 */
final class PeriodoPorDia
{
    /** Limita `$coluna` aos dias de `$inicio` a `$fim` (inclusive). Limite nulo = aberto. */
    public static function aplicar(Builder $query, string $coluna, mixed $inicio, mixed $fim): Builder
    {
        if ($inicio !== null && $inicio !== '') {
            $query->where($coluna, '>=', Carbon::parse($inicio)->startOfDay()->format('Y-m-d H:i:s'));
        }
        if ($fim !== null && $fim !== '') {
            $query->where($coluna, '<', Carbon::parse($fim)->startOfDay()->addDay()->format('Y-m-d H:i:s'));
        }

        return $query;
    }

    /** Só o dia `$dia`. */
    public static function noDia(Builder $query, string $coluna, mixed $dia): Builder
    {
        return self::aplicar($query, $coluna, $dia, $dia);
    }
}
