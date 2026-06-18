<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * M1.4 — Cast de valor monetário/decimal BR, componente compartilhado da UX nova.
 *
 * Substitui o vai-e-vem manual dos helpers legados (requestNumeroDecimalOracle /
 * insertNumeroDecimalOracle) que convertiam "1.000,00" ↔ float espalhado pelos
 * controllers. Aqui o atributo do model é sempre float em PHP e numeric no banco;
 * a APRESENTAÇÃO em pt-BR fica na view/Resource (não no model).
 *
 * Uso no model:
 *   protected $casts = ['valor' => \App\Casts\MoedaBR::class];
 *
 * Aceita na escrita tanto float quanto string BR ("1.234,56") ou US ("1234.56").
 */
class MoedaBR implements CastsAttributes
{
    /** Banco → PHP: sempre float (ou null). */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (float) $value;
    }

    /** PHP/entrada → banco: normaliza string BR/US para número. */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $str = trim((string) $value);

        // Formato BR "1.234,56": remove separador de milhar '.' e troca ',' por '.'.
        if (preg_match('/^\d{1,3}(\.\d{3})*(,\d+)?$/', $str)) {
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
        } elseif (str_contains($str, ',') && ! str_contains($str, '.')) {
            // "1234,56" → "1234.56"
            $str = str_replace(',', '.', $str);
        }
        // demais casos: assume já-numérico ("1234.56").

        return is_numeric($str) ? (float) $str : null;
    }
}
