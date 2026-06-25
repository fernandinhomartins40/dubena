<?php

namespace App\Domain\Cobranca\Cnab;

/**
 * Helpers de CNAB/boleto (F08): dígitos verificadores (módulo 10 e 11), formatação
 * de campos de largura fixa e fator de vencimento FEBRABAN. PHP puro e testável —
 * é a matemática que o legado fazia via lib; aqui nativa, sem dependência externa.
 */
final class CnabHelper
{
    /** Data base do fator de vencimento FEBRABAN (07/10/1997). */
    private const BASE_FATOR = '1997-10-07';

    /** Preenche à esquerda com zeros (numérico). */
    public static function numero(int|string $v, int $tamanho): string
    {
        return str_pad(substr(preg_replace('/\D/', '', (string) $v), 0, $tamanho), $tamanho, '0', STR_PAD_LEFT);
    }

    /** Preenche à direita com espaços e maiúsculas (alfanumérico). */
    public static function texto(string $v, int $tamanho): string
    {
        $v = strtoupper(self::semAcento($v));
        $v = preg_replace('/[^A-Z0-9 .,\-\/]/', ' ', $v);

        return str_pad(substr($v, 0, $tamanho), $tamanho, ' ', STR_PAD_RIGHT);
    }

    /** Valor monetário → inteiro em centavos, com largura fixa. */
    public static function valor(float $v, int $tamanho = 15): string
    {
        return self::numero((int) round($v * 100), $tamanho);
    }

    /** Dígito verificador módulo 10 (linha digitável). */
    public static function modulo10(string $num): int
    {
        $num = preg_replace('/\D/', '', $num);
        $soma = 0;
        $peso = 2;
        for ($i = strlen($num) - 1; $i >= 0; $i--) {
            $p = (int) $num[$i] * $peso;
            $soma += $p > 9 ? ((int) ($p / 10) + ($p % 10)) : $p;
            $peso = $peso === 2 ? 1 : 2;
        }
        $resto = $soma % 10;

        return $resto === 0 ? 0 : 10 - $resto;
    }

    /**
     * Dígito verificador módulo 11. $base define os pesos cíclicos (2..base).
     * $paraBarras=true aplica a regra do código de barras (DV 0/1/10 → 1).
     */
    public static function modulo11(string $num, int $base = 9, bool $paraBarras = false): int
    {
        $num = preg_replace('/\D/', '', $num);
        $soma = 0;
        $peso = 2;
        for ($i = strlen($num) - 1; $i >= 0; $i--) {
            $soma += (int) $num[$i] * $peso;
            $peso = $peso === $base ? 2 : $peso + 1;
        }

        if ($paraBarras) {
            $resto = $soma % 11;
            $dv = 11 - $resto;

            return in_array($dv, [0, 10, 11], true) ? 1 : $dv;
        }

        $resto = $soma % 11;
        $dv = 11 - $resto;

        return $dv >= 10 ? 0 : $dv;
    }

    /** Fator de vencimento FEBRABAN (4 dígitos) a partir de uma data Y-m-d. */
    public static function fatorVencimento(string $vencimento): string
    {
        $base = new \DateTimeImmutable(self::BASE_FATOR);
        $venc = new \DateTimeImmutable($vencimento);
        $dias = (int) $base->diff($venc)->format('%a');
        if ($venc < $base) {
            return '0000';
        }
        // Após 21/02/2025 o fator "reinicia" (regra FEBRABAN 9999→1000); aplica módulo.
        $fator = $dias + 1000;
        if ($fator > 9999) {
            $fator = ($fator - 1000) % 9000 + 1000;
        }

        return self::numero($fator, 4);
    }

    /**
     * Monta a linha digitável (47 posições) a partir do código de barras (44).
     * Campos 1-3 com DV módulo 10; campo 4 é o DV geral; campo 5 fator+valor.
     */
    public static function linhaDigitavel(string $codigoBarras): string
    {
        $b = preg_replace('/\D/', '', $codigoBarras);
        if (strlen($b) !== 44) {
            return $b;
        }

        // Campo 1: posições 1-4 + 20-24 (AAABC.CCCCX)
        $c1 = substr($b, 0, 4).substr($b, 19, 5);
        $c1 .= self::modulo10($c1);
        // Campo 2: 25-34
        $c2 = substr($b, 24, 10);
        $c2 .= self::modulo10($c2);
        // Campo 3: 35-44
        $c3 = substr($b, 34, 10);
        $c3 .= self::modulo10($c3);
        // Campo 4: DV geral (posição 5 do código de barras)
        $c4 = substr($b, 4, 1);
        // Campo 5: fator de vencimento (6-9) + valor (10-19)
        $c5 = substr($b, 5, 14);

        return sprintf(
            '%s.%s %s.%s %s.%s %s %s',
            substr($c1, 0, 5), substr($c1, 5),
            substr($c2, 0, 5), substr($c2, 5),
            substr($c3, 0, 5), substr($c3, 5),
            $c4, $c5,
        );
    }

    private static function semAcento(string $v): string
    {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $v);

        return $t !== false ? $t : $v;
    }
}
