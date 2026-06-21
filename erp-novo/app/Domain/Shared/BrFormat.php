<?php

namespace App\Domain\Shared;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Conversões de formato BR ↔ tipos nativos.
 *
 * Substitui os ~13 helpers `*Oracle` do legado (insertNumeroDecimalOracle,
 * requestPercentualOracle, insertDataOracle, etc.). No backend novo os valores
 * são SEMPRE numéricos/date; esta classe existe para UM caso: o ETL lê o legado
 * (que guarda "R$ 1.234,56" / "12,50 %" / "31/12/2025") e normaliza para número/date.
 * A formatação de exibição é responsabilidade do frontend, não do backend.
 */
final class BrFormat
{
    /** "1.234,56" | "R$ 1.234,56" | "12,50 %" | 1234.56 → float 1234.56 */
    public static function toNumber(string|int|float|null $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }

        $s = trim($valor);
        // remove qualquer coisa que não seja dígito, vírgula, ponto ou sinal
        $s = preg_replace('/[^\d,.\-]/', '', $s) ?? '';
        if ($s === '' || $s === '-') {
            return null;
        }

        // formato BR: ponto = milhar, vírgula = decimal
        if (str_contains($s, ',')) {
            $s = str_replace('.', '', $s);   // tira milhar
            $s = str_replace(',', '.', $s);  // vírgula → ponto decimal
        }

        return is_numeric($s) ? (float) $s : null;
    }

    /** "dd/mm/aaaa [hh:mm[:ss]]" → CarbonImmutable (null se vazio/ inválido). */
    public static function toDate(?string $valor): ?CarbonImmutable
    {
        if ($valor === null || trim($valor) === '') {
            return null;
        }
        $valor = trim($valor);

        foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d'] as $fmt) {
            try {
                $dt = CarbonImmutable::createFromFormat('!'.$fmt, $valor);
            } catch (\Throwable) {
                continue; // formato não casa (modo estrito lança) → tenta o próximo
            }
            if ($dt instanceof CarbonImmutable && $dt->format($fmt) === $valor) {
                return $dt;
            }
        }

        // fallback tolerante
        try {
            return CarbonImmutable::parse($valor);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Para exibição/relatório legado-compatível, quando necessário: número → "1.234,56". */
    public static function format(float|int|null $valor, int $casas = 2): string
    {
        return number_format((float) ($valor ?? 0), $casas, ',', '.');
    }

    public static function formatDate(?DateTimeInterface $data, string $fmt = 'd/m/Y'): ?string
    {
        return $data?->format($fmt);
    }
}
