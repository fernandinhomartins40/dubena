<?php

use App\Helpers\Util;
use Illuminate\Support\Collection;

if (! function_exists('encodeSecret')){
    function encodeSecret($txt)
    {
        $secret = base64_encode(hash_hmac('sha256', $txt, 'secret', true));
        return $secret;
    }
}

if (! function_exists('responseSuccess')) {

    /**
     * @param $data
     * @param $msg
     * @return \Illuminate\Http\JsonResponse
     */
    function responseSuccess($data = [], $msg = 'Sucesso!')
    {
        Util::logResponse($msg, "success", $data);
        return response()->json([
            'data'      => $data,
            'msg'       => utf8Format($msg),
            'status'    => 'OK'
        ], 200);
    }
}

if (! function_exists('responseError')) {
    /**
     * @param $msg
     * @param int $status
     * @return \Illuminate\Http\JsonResponse
     */
    function responseError($msg, $status = 200)
    {
        Util::logResponse($msg, "error");
        return response()->json([
            'msg'       => utf8Format($msg),
            'status'    => "NOK"
        ], $status);
    }
}

if (! function_exists('responseReject')) {

    /**
     * @param $msg
     * @param string $rejection
     * @param int $status
     * @return \Illuminate\Http\JsonResponse
     */
    function responseReject($msg, $rejection = "DEFAULT", $status = 200)
    {
        Util::logResponse($msg, "warn");
        Util::log("Response rejected: " . $msg . PHP_EOL);
        return response()->json([
            'msg'           => utf8Format($msg),
            'status'        => "OPS",
            'rejection'     => $rejection
        ], $status);
    }
}

if (! function_exists('getOrFail')) {

    /**
     * @param $key
     * @param null $default
     * @param bool $msg
     * @return mixed
     * @throws Exception
     */
    function getOrFail($key, $default = null, $msg = false)
    {
        $field = Input::get($key, $default);

        if (! $msg) {
            $msg = "Key {" . $key . "} inválida.";
        }

        if (! $field) {
            throw new Exception($msg);
        }

        return $field;
    }
}

if (! function_exists('setTimezone')) {

    /**
     *
     */
    function setTimezone()
    {
        $user = Auth::user();
        if (isset($user->uf) && $user->uf) {
            $timezone = getTimezone($user->uf);
        } else {
            $timezone = config('app.timezone', "America/Sao_Paulo");
        }
        date_default_timezone_set($timezone);
    }

}

if (! function_exists("isAssoc")) {

    /**
     * @param array $arr
     * @return bool
     */
    function isAssoc(array $arr)
    {
        if (array() === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}

if (! function_exists('getTimezone')) {

    /**
     * @param $uf
     * @return mixed
     */
    function getTimezone($uf)
    {
        return collect([
            'AC' => 'America/Rio_branco',
            'AL' => 'America/Maceio',
            'AP' => 'America/Belem',
            'AM' => 'America/Manaus',
            'BA' => 'America/Bahia',
            'CE' => 'America/Fortaleza',
            'DF' => 'America/Sao_Paulo',
            'ES' => 'America/Sao_Paulo',
            'GO' => 'America/Sao_Paulo',
            'MA' => 'America/Fortaleza',
            'MT' => 'America/Cuiaba',
            'MS' => 'America/Campo_Grande',
            'MG' => 'America/Sao_Paulo',
            'PR' => 'America/Sao_Paulo',
            'PB' => 'America/Fortaleza',
            'PA' => 'America/Belem',
            'PE' => 'America/Recife',
            'PI' => 'America/Fortaleza',
            'RJ' => 'America/Sao_Paulo',
            'RN' => 'America/Fortaleza',
            'RS' => 'America/Sao_Paulo',
            'RO' => 'America/Porto_Velho',
            'RR' => 'America/Boa_Vista',
            'SC' => 'America/Sao_Paulo',
            'SE' => 'America/Maceio',
            'SP' => 'America/Sao_Paulo',
            'TO' => 'America/Araguaia',
        ])->get($uf);
    }

}

if (! function_exists('getAddressFromLatLng')) {

    /**
     * @param $lat
     * @param $lng
     * @param null $key
     * @return mixed
     * @throws Exception
     */
    function getAddressFromLatLng($lat, $lng, $key = null)
    {
        try {

            if (! $key) {
                $key = env("GMAPS_KEY", null);
                if (is_null($key)) {
                    throw new Exception("Não foi possível buscar os dados, a chave de acesso do serviço do mapa não foi encontrada");
                }
            }

            $urlMaps = env("GMAPS_URL", null);
            if (is_null($urlMaps)) {
                throw new Exception("Não foi possível buscar os dados, a url serviço do mapa não foi encontrada");
            }

            $url = $urlMaps . 'json?latlng=' . ((float) $lat) . "," . ((float) $lng) . '&sensor=false&key=' . $key;
            $contentJson = json_decode(file_get_contents($url));
            $results = $contentJson->results;

            if ($contentJson->status === "OK" && count($results) > 0) {
                return $results;
            } else {
                throw new Exception("Endereço não encontrado");
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }
}

if (! function_exists('moneyToDecimal')) {

    function moneyToDecimal($value, $decimals = 2)
    {
        $value = str_replace('R$ ', '', $value);
        $value = str_replace('.', ',', $value);
        $value = str_replace(',', '.', $value);

        return (float) sprintf("%0." . $decimals . "f", $value);
    }

}

if (! function_exists('floatToMoney')) {

    /**
     * @param $value
     * @param int $decimals
     * @return string
     */
    function floatToMoney($value, $decimals = 2)
    {
        return 'R$ ' . number_format($value, $decimals, ',', '.');
    }

}

if (!function_exists('mask')) {

    function mask($val, $mask)
    {
        $maskared = '';
        $k = 0;
        for ($i = 0; $i <= strlen($mask) - 1; $i++) {
            if ($mask[$i] == '#') {
                if (isset($val[$k]))
                    $maskared .= $val[$k++];
            } else {
                if (isset($mask[$i]))
                    $maskared .= $mask[$i];
            }
        }
        return $maskared;
    }

}

if (! function_exists('validateParameters')) {

    /**
     * @param $arr
     * @param $needed
     * @return array
     * @throws Exception
     */
    function validateParameters($arr, $needed)
    {
        $return = [];
        if (! is_array($arr)) {
            $arr = (array) $arr;
        }
        foreach ($needed as $key) {
            if (! array_key_exists($key, $arr)) {
                throw new Exception("Parâmetro " . $key . " é obrigatório para gerar os itens pedidos.");
            } else {
                $return[$key] = $arr[$key];
            }
        }

        return $return;
    }
}

if (! function_exists('convertImgPath')) {

    /**
     * @param $collection
     * @param string $key
     * @return mixed
     * @throws Exception
     */
    function convertImgPath($collection, $key = "caminhoimagem")
    {
        $path = Storage::disk('images')->path('');

        foreach ($collection as &$item) {
            hasInCollection($key, $item);

            $file = $path . $item->{$key};
            if (! file_exists($file)) {
                $file = $path . "no-image.png";
            }
            if (file_exists($file)) {
                $item->{$key} = imgToBase64($file);
            }
        }
        return $collection;
    }
}

if (! function_exists('imgToBase64')) {

    /**
     * @param $file
     * @return string
     */
    function imgToBase64($file)
    {
        $type = pathinfo($file, PATHINFO_EXTENSION);
        $data = file_get_contents($file);
        return 'data:image/' . $type . ';base64,' . base64_encode($data);
    }
}

if (! function_exists('hasInCollection')) {

    /**
     * @param $key
     * @param $item
     * @throws Exception
     */
    function hasInCollection($key, $item)
    {
        if (! isset($item->{$key})) {
            throw new Exception("Column " . $key . " not found in collection");
        }
    }
}

if (! function_exists('hashClientSecret')) {

    /**
     * @param $password
     * @return string
     */
    function hashClientSecret($password)
    {
        return base64_encode(hash_hmac('sha256', $password, 'secret', true));
    }
}

if (! function_exists('redirectHomeWithMessage')) {

    /**
     * @param $message
     * @param string $type
     * @return \Illuminate\Http\RedirectResponse
     */
    function redirectHomeWithMessage($message, $type = "message_danger")
    {
        Session::flash($type, $message);
        return redirect()->route("home");
    }
}

if (! function_exists("replaceSpecialChars")) {

    /**
     * @param string $string
     * @return bool
     */
    function replaceSpecialChars(string $string)
    {
        return preg_replace('/[^a-zA-Z0-9_ -]/s','', $string);
    }
}

if (! function_exists("onlyNumbers")) {

    /**
     * @param string $string
     * @return bool
     */
    function onlyNumbers(string $string)
    {
        return preg_replace('/[^0-9]/s', '', $string);
    }
}

if (!function_exists('replaceAccents')) {

    /**
     * Removes special chars.
     * @param string $text
     * @return string
     */
    function replaceAccents($text)
    {
        $validChars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ /,.- @:&*+_<>()!?'$%1234567890";
        $caracterComAcento = str_split_unicode("áàãâäéèêëíìîïóòõôöúùûüçÁÀÃÂÄÉÈÊËÍÌÎÏÓÒÕÖÔÚÙÛÜÇ");
        $caracterSemAcento = str_split("aaaaaeeeeiiiiooooouuuucAAAAAEEEEIIIIOOOOOUUUUC");
        $textFormated = "";
        $text = str_replace($caracterComAcento, $caracterSemAcento, $text);
        for ($i = 0; $i < strlen($text); $i++) {
            $char = $text[$i];
            if (strpos($validChars, $char) !== false)
                $textFormated .= $char;
        }
        return $textFormated;
    }
}

if (!function_exists('str_split_unicode')) {

    /**
     *
     * @param string $str
     * @param int $l
     * @return mixed
     */
    function str_split_unicode($str, $l = 0)
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

}

if (!function_exists('str_encode_to_query')) {

    /**
     * remove espaços, e joga tudo pra lower para conseguir buscar na query
     *
     * @param string $query
     * @return string
     */
    function str_encode_to_query($query)
    {
        return replaceAccents(utf8_encode(strtolower(utf8_decode(rtrim(ltrim($query))))));
    }

}

if (!function_exists('getInputToQuerySearch')) {

    /**
     * @return string
     * @throws Exception
     */
    function getInputToQuerySearch()
    {
        $q = str_encode_to_query(e(Input::get("q", null)));

        if (! $q) {
            throw new Exception("Parâmetros de busca informados incorretamente");
        }

        return $q;
    }
}

if (!function_exists('rawSpecialChars')) {

    /**
     * retorna uma string ou instancia de DB::raw() para pesquisas sem diferenciar acentos no oracle
     * @param string $column
     * @param string $description
     * @return string or DB::raw();
     */
    function rawSpecialChars($column, $description)
    {
        $str = "replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(replace( ".
            "replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(replace( ".
            "replace(replace(replace(replace($column, 'à', 'a'), 'á', 'a'), 'â', 'a'), 'ã', 'a'), 'ä', 'a'), ".
            " 'å', 'a'), 'é', 'e'), 'è', 'e'), 'ê', 'e'), 'ë', 'e'), 'ï', 'i'), 'ì', 'i'), 'í', 'i'), 'î', 'i'), ".
            " 'ó', 'o'), 'ò', 'o'), 'ô', 'o'), 'ö', 'o'), 'õ', 'o'), 'ú', 'u'), 'ù', 'u'), 'û', 'u'), 'ü', 'u'), ".
            " 'ý', 'y'), 'ÿ', 'y'), 'ç', 'c'), 'ñ', 'n')" . " like '%" . str_encode_to_query($description) . "%'";

        return $str;
    }
}

if (! function_exists("utf8Format")) {

    /**
     * @param $param
     * @return null|string|string[]
     */
    function utf8Format($param)
    {
        if (is_string($param)) {
            $param = strEncode($param);
        } elseif ($param instanceof Collection) {
            $param = strEncode($param->toJson());
        } elseif (is_array($param)) {
            $param = arrayEncode($param);
        } elseif (is_object($param)) {
            $param = arrayEncode((array) $param);
        }
        return $param;
    }
}

if (! function_exists("strEncode")) {

    /**
     * @param $val
     * @return null|string|string[]
     */
    function strEncode($val)
    {
        $val = mb_convert_encoding($val, 'UTF-8', 'UTF-8');
        return $val;
    }
}

if (! function_exists("arrayEncode")) {

    /**
     * @param $param
     * @return mixed
     */
    function arrayEncode($param)
    {
        array_walk_recursive($param, function (&$val) {
            if (is_string($val)) {
                $val = strEncode($val);
            }
        });
        return $param;
    }
}

if (! function_exists("firstWord")) {

    /**
     * @param $name
     * @param string $state
     * @return string
     */
    function firstWord($name, $state = "U")
    {
        $exploded = explode(" ", $name)[0];
        switch (strtoupper($state)) {
            case "U":
                return strtoupper($exploded);
            case "L":
                return strtolower($exploded);
            default:
                return $exploded;
        }
    }
}

if (! function_exists("strNullToNullValue")) {

    /**
     * @param array $array
     * @return array
     */
    function strNullToNullValue(array $array)
    {
        foreach ($array as &$item) {
            if ($item === "null") {
                $item = null;
            }
        }

        return $array;
    }
}

if (! function_exists("utf8FormatJson")) {

    /**
     * @param $param
     * @return null|string|string[]
     */
    function utf8FormatJson($param)
    {
        if (is_string($param)) {
            $param = strEncode($param);
        } elseif ($param instanceof Collection) {
            $param = strEncode($param->toJson());
        } elseif (is_array($param)) {
            $param = arrayEncode($param);
        } elseif (is_object($param)) {
            $param = arrayEncode((array) $param);
        }
        return $param;
    }
}


if (! function_exists("urlBuilder")) {

    /**
     * @param $formParams
     * @return string
     */
    function urlBuilder($formParams)
    {
        $params = "";
        $glue = "?";
        foreach ($formParams as $key => $formParam) {
            $formParam = utf8Format($formParam);
            $params .= $glue . $key . "=" . $formParam;
            $glue = "&";
        }
        return $params;
    }
}
