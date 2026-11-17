<?php

namespace App\Domain\Identidade;

/**
 * CPF e CNPJ: dígito verificador e forma canônica (só dígitos).
 *
 * PIE-35: o cadastro aceitava "111.111.111-11", "123" e
 * "123.456.789-0123456". CPF inválido no cadastro só aparece quando a nota é
 * rejeitada pela SEFAZ — com o botijão já entregue. E a forma gravada importa:
 * o ETL grava só dígitos e o `XmlNfeBuilder` usa o valor como está no `<CPF>`,
 * então "023.674.920-78" com máscara viraria rejeição de schema.
 */
final class DocumentoFiscal
{
    /** Só dígitos, ou null se não sobrou nada. */
    public static function canonico(?string $valor): ?string
    {
        $d = NormalizadorTexto::digitos($valor);

        return $d === '' ? null : $d;
    }

    public static function cpfValido(?string $valor): bool
    {
        $cpf = NormalizadorTexto::digitos($valor);

        // Sequência repetida passa na conta do dígito e não é CPF de ninguém.
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $dv = ((10 * $soma) % 11) % 10;
            if ((int) $cpf[$t] !== $dv) {
                return false;
            }
        }

        return true;
    }

    public static function cnpjValido(?string $valor): bool
    {
        $cnpj = NormalizadorTexto::digitos($valor);

        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        foreach ([12, 13] as $t) {
            $pesos = $t === 12 ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2] : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $cnpj[$i] * $pesos[$i];
            }
            $resto = $soma % 11;
            $dv = $resto < 2 ? 0 : 11 - $resto;
            if ((int) $cnpj[$t] !== $dv) {
                return false;
            }
        }

        return true;
    }
}
