<?php

namespace App\Processors\Sped;

use App\Services\CarbonCustom as Carbon;

/**
 * 
 */
final class Util
{

    /**
     * verifica se a variável é nula ou vazia
     * @param string $value
     * @return bool
     */
    public static function isNullOrEmpty($value)
    {
        return strlen($value) === 0;
    }

    /**
     * retorna o mês de uma data
     * @param string $date
     * @return string
     */
    public static function getMonth($date)
    {
        return self::extractFromDate($date, 'month');
    }

    /**
     * retorna o ano de uma data
     * @param mixed $date
     * @return string
     */
    public static function getYear($date)
    {
        return self::extractFromDate($date, 'year');
    }

    /**
     * 
     * @param mixed $date
     * @param string $value
     * @return string
     */
    public static function extractFromDate($date, $value)
    {
        if ($date instanceof Carbon) {
            return $date->{$value};
        }
        if (strlen($date) === 0) {
            return "";
        }
        return self::createDate($date)->{$value};
    }

    /**
     * 
     * verifica se as duas datas tem o mesmo mês
     * @param string $d1
     * @param string $d2
     * @return bool
     */
    public static function isEqualsMonthYear($d1, $d2)
    {
        return Util::getMonth($d1) == Util::getMonth($d2) && Util::getYear($d1) == Util::getYear($d2);
    }

    /**
     * formata a data para o padrao dmY
     * @param string $date
     * @return string
     */
    public static function dateFormat($date)
    {
        if ($date instanceof Carbon) {
            return $date->format('dmY');
        }

        if (strlen($date) === 0) {
            return "";
        }

        return self::createDate($date)->format('dmY');
    }

    public static function dateToUS($date)
    {
        if ($date instanceof Carbon) {
            return $date->format('dmY');
        }

        if (strlen($date) === 0) {
            return "";
        }

        return self::createDate($date)->format('Y-m-d H:i:s');
    }

    public static function createDate($date)
    {
        if ($date instanceof Carbon) {
            return $date->format('Y-m-d H:i:s');
        }

        if (strlen($date) === 0) {
            return "";
        }
        if (strpos($date, '/') === false && strpos($date, '-') === false) {
            $date = substr($date, 0, 2) . '/' . substr($date, 2, 2) . '/' . substr($date, 4, 4);
        }
        if (strpos($date, '/') !== false) {
            $format = "d/m/Y";
        } else {
            $format = "Y-m-d";
        }

        if (strlen($date) <= 10) {
            $date .= "00:00:00";
        } else if (strlen($date) <= 16) {
            $date .= ":00";
        }

        return Carbon::createFromFormat("$format H:i:s", $date);
    }

    public static function replaceSpecialChars($text, $strict = false, $whiteSpaces = false)
    {
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        // replace non letter or digits by -
        $text = preg_replace('~[^\\pL\d.]+~u', '-', $text);

        // trim
        $text = trim($text, '-');
        setlocale(LC_CTYPE, 'en_GB.utf8');
        // transliterate
        if (function_exists('iconv'))
            $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // remove unwanted characters
        $text = preg_replace('~[^-\w.]+~', '', $text);
        if (empty($text))
            return '';

        if ($strict)
            $text = str_replace(".", "", $text);

        $text = str_replace('-', $whiteSpaces ? "" : " ", $text);

        return $text;
    }

    public static function replaceAccent($text)
    {
        return replaceAccents($text);
    }

    private static function str_split_unicode($str, $l = 0)
    {
        if ($l > 0) {
            $ret = array();
            $len = mb_strlen($str, "UTF-8");
            for ($i = 0; $i < $len; $i += $l) {
                $ret[] = mb_substr($str, $i, $l, "UTF-8");
            }
            return $ret;
        }
        return preg_split("//u", $str, -1, PREG_SPLIT_NO_EMPTY);
    }

    public static function validateCPF($cpf)
    {
        $cpf = preg_replace('/[^0-9]/', '', (string) $cpf);

        // Valida tamanho
        if (strlen($cpf) != 11) {
            return false;
        }
        // Calcula e confere primeiro dígito verificador
        for ($i = 0, $j = 10, $sum = 0; $i < 9; $i++, $j--) {
            $sum += $cpf{$i} * $j;
        }
        $resto = $sum % 11;
        if ($cpf{9} != ($resto < 2 ? 0 : 11 - $resto)) {
            return false;
        }
        // Calcula e confere segundo dígito verificador
        for ($i = 0, $j = 11, $sum = 0; $i < 10; $i++, $j--) {
            $sum += $cpf{$i} * $j;
        }
        $resto = $sum % 11;
        $notValid = collect([
            '00000000000',
            '11111111111',
            '22222222222',
            '33333333333',
            '44444444444',
            '55555555555',
            '66666666666',
            '77777777777',
            '88888888888',
            '99999999999'
        ]);

        if (!is_null($notValid->get($cpf))) {
            return false;
        }

        return $cpf{10} == ($resto < 2 ? 0 : 11 - $resto);
    }

    public static function validateCNPJ($cnpj)
    {
        $cnpj = preg_replace('/[^0-9]/', '', (string) $cnpj);
        // Valida tamanho
        if (strlen($cnpj) != 14) {
            return false;
        }
        // Valida primeiro dígito verificador
        for ($i = 0, $j = 5, $soma = 0; $i < 12; $i++) {
            $soma += $cnpj{$i} * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }
        $resto = $soma % 11;
        if ($cnpj{12} != ($resto < 2 ? 0 : 11 - $resto))
            return false;
        // Valida segundo dígito verificador
        for ($i = 0, $j = 6, $soma = 0; $i < 13; $i++) {
            $soma += $cnpj{$i} * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }
        $notValid = collect([
            '00000000000000',
            '11111111111111',
            '22222222222222',
            '33333333333333',
            '44444444444444',
            '55555555555555',
            '66666666666666',
            '77777777777777',
            '88888888888888',
            '99999999999999'
        ]);
        $resto = $soma % 11;

        if (!is_null($notValid->get($cnpj))) {
            return false;
        }

        return $cnpj{13} == ($resto < 2 ? 0 : 11 - $resto);
    }

    public static function fillStrWith($str, $len, $fillWith)
    {
        if (strlen($str) >= $len) {
            return $str;
        }
        $fill = "";
        for ($i = strlen($str); $i < $len; $i++) {
            $fill .= $fillWith;
        }

        return $fill . $str;
    }

    public static function hasIn($str, array $values, bool $strict = false)
    {
        if (count($values) === 1 && strpos($values[0], '-', $strict) !== false) {
            $values = explode('-', $values[0]);
        }
        return in_array($str, $values, $strict);
    }

    public static function numberFormat($number, $decimals = 2)
    {
        return number_format((float) $number, $decimals, ",", '');
    }

    public static function inBetween($value, $min, $max)
    {
        return $value <= $max && $value >= $min;
    }

    public static function isAssoc(array $arr)
    {
        if (array() === $arr) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    public static function toAssoc($old, $a = false)
    {
        if (!Util::isAssoc($old)) {
            return $old;
        }
        $args = [];
        foreach ($old as $value) {
            $args[] = $value;
        }
        return $args;
    }

    public static function pregReplaceCnpjCpf($value)
    {
        $cnpf = preg_replace('/[^0-9]/', '', (string) $value);
        return $cnpf;
    }

    public static function getIndPgto($tipo)
    {
        return !is_null($tipo) ? (in_array($tipo, ['0', '2']) ? '0' : '1') : "2";
    }

    public static function geraDevolST($cfop)
    {
        return in_array($cfop, ['1410', '1411', '1414', '1415', '1660', '1661', '1662',
            '2410', '2411', '2414', '2415', '2660', '2661', '2662']);
    }

    public static function geraRessarcST($cfop)
    {
        return in_array($cfop, ['1603', '2603']);
    }

    public static function geraRetencaoST($cfop)
    {
        return static::hasIn(substr($cfop, 0, 1), ["5-6"]);
    }

    public static function geraDebitoICMS($cfop)
    {
        $cfop = trim($cfop);
        return (static::hasIn(substr($cfop, 0, 1), ["5-6-7"]) && $cfop != "5605") || $cfop == "1605";
    }

    public static function geraCredICMS($cfop)
    {
        $cfop = trim($cfop);
        return (static::hasIn(substr($cfop, 0, 1), ["1-2-3"]) && $cfop != "1605") || $cfop == "5605";
    }

}
