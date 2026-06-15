<?php

use Illuminate\Support\Collection;

if (!function_exists('imageResize')) {

    function imageResize($file, $w, $h, $crop = FALSE)
    {
        $percent = 0.08;
        list($width, $height, $type) = getimagesize($file);
        $newwidth = $width * $percent;
        $newheight = $height * $percent;
        if ($type === 1)
            $source = imagecreatefromgif($file);
        if ($type === 2)
            $source = imagecreatefromjpeg($file);
        if ($type === 3)
            $source = imagecreatefrompng($file);
        $thumb = imagecreatetruecolor($newwidth, $newheight);
        imagecopyresized($thumb, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
        if ($type === 1)
            $source = imagegif($thumb, $file, 100);
        if ($type === 2)
            $source = imagejpeg($thumb, $file, 100);
        if ($type === 3)
            $source = imagepng($thumb, $file, 100);
        return $thumb;
    }

}
if (!function_exists('my_mb_ucfirst')) {

    function my_mb_ucfirst($str)
    {
        $fc = mb_strtoupper(mb_substr($str, 0, 1));
        return $fc . mb_strtolower(mb_substr($str, 1));
    }

}

if (!function_exists('requestDataHoraFileName')) {

    function requestDataHoraFileName($dataEntrada)
    {
        $dataSaida = '';
        if (strlen($dataEntrada) > 0) {
            $data = explode(' ', $dataEntrada);
            $dt = explode('-', $data[0]);
            $dataSaida = $dt[0] . $dt[1] . $dt[2];
            if (isset($data[1])) {
                $hr = explode(':', $data[1]);
                if (count($hr) > 0)
                    $dataSaida .= $hr[0] . $hr[1];
            }
        }
        return $dataSaida;
    }

}

if (!function_exists('insertDataOracle')) {

    function insertDataOracle($dataEntrada)
    {
        $dataSaida = '';
        if (strlen($dataEntrada) > 0) {
            $data = explode(' ', $dataEntrada);
            $dt = explode('/', $data[0]);
            $dataSaida = $dt[2] . "-" . $dt[1] . "-" . $dt[0];
            if (isset($data[1])) {
                $hr = explode(':', $data[1]);
                if (count($hr) > 0)
                    $hr[3] = isset($hr[3]) && $hr[3] !== "" ? $hr[3] : '00';
                $dataSaida .= ' ' . $hr[0] . ":" . $hr[1] . ":" . $hr[3];
            }
        }
        return $dataSaida;
    }

}

if (!function_exists('requestDataOracle')) {

    function requestDataOracle($dataEntrada, $hora = true, $fullYear = true, $sec = true)
    {
        $dataSaida = '';
        if (strlen($dataEntrada) > 0) {
            $data = explode(' ', $dataEntrada);

            if (empty($data[0])) return $dataEntrada;

            $dt = explode('-', $data[0]);

            if ($fullYear) {
                $dataSaida = $dt[2] . "/" . $dt[1] . "/" . $dt[0];
            } else {
                $dataSaida = $dt[2] . "/" . $dt[1] . "/" . substr($dt[0], 2, 4);
            }
            if (isset($data[1])) {
                $hr = explode(':', $data[1]);
                if ($hora) {
                    if (count($hr) > 0) {
                        if ($sec)
                            $dataSaida .= ' ' . $hr[0] . ":" . $hr[1] . ":" . $hr[2];
                        else
                            $dataSaida .= ' ' . $hr[0] . ":" . $hr[1];
                    }
                }
            }
        }
        return $dataSaida;
    }

}

if (!function_exists('requestDataOracleSemSeg')) {

    function requestDataOracleSemSeg($dataEntrada, $hora = true, $fullYear = true)
    {
        $dataSaida = '';
        if (strlen($dataEntrada) > 0) {
            $data = explode(' ', $dataEntrada);
            $dt = explode('-', $data[0]);
            if ($fullYear) {
                $dataSaida = $dt[2] . "/" . $dt[1] . "/" . $dt[0];
            } else {
                $dataSaida = $dt[2] . "/" . $dt[1] . "/" . substr($dt[0], 2, 4);
            }
            if (isset($data[1])) {
                $hr = explode(':', $data[1]);
                if ($hora) {
                    if (count($hr) > 0)
                        $dataSaida .= ' ' . $hr[0] . ":" . $hr[1];
                }
            }
        }
        return $dataSaida;
    }

}

if (!function_exists('requestDataOracleSemHora')) {

    function requestDataOracleSemHora($dataEntrada)
    {
        $dataSaida = '';
        if (strlen($dataEntrada) > 0) {
            $data = explode(' ', $dataEntrada);
            $dt = explode('-', $data[0]);
            $dataSaida = $dt[2] . "/" . $dt[1] . "/" . $dt[0];
        }
        return $dataSaida;
    }

}

//// valida tipo numero para armazenar no banco
if (!function_exists('ajustarNumero')) {

    function ajustarNumero($valor)
    {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
        return $valor;
    }

}

//// troca as virgulas por pontos
if (!function_exists('preparaMoeda')) {

    function preparaMoeda($valor)
    {
        $verificaPonto = ".";
        if (strpos("[" . $valor . "]", "$verificaPonto")):
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        else:
            $valor = str_replace(',', '.', $valor);
        endif;

        return $valor;
    }

}

//// retira todos os pontos e virgula do campo para inserir um número inteiro no banco
if (!function_exists('insertNumeroDecimalOracle')) {

    function insertNumeroDecimalOracle($valor)
    {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
        $valor = str_replace('R$ ', '', $valor);
        return floatVal($valor);
    }

}

if (!function_exists('formatDecimalPlaces')) {

    /**
     * @param $valor
     * @param int $precision
     * @param bool $trunc
     * @return string
     */
    function formatDecimalPlaces($valor, $precision = 2, $trunc = false)
    {
        if (! is_numeric($valor) && str_contains($valor, "R$")) {
            $valor = insertNumeroDecimalOracle($valor);
        }
        if ($trunc) {
            return sprintf('%0.' . $precision . 'f',
                trunc($valor, $precision)
            );
        } else {
            return sprintf('%0.' . $precision . 'f',
                round($valor, $precision)
            );
        }
    }

}

if (!function_exists('trunc')) {

    function trunc($float, $prec = 2)
    {
        $modifier = (int) str_pad("1", $prec + 1, "0");
        return floor($float * $modifier) / $modifier;
    }

}

//essa função é para quando um número decimal for gravado no banco
if (!function_exists('requestNumeroDecimalOracle')) {

    function requestNumeroDecimalOracle($valor)
    {
        if ($valor === '0' or $valor === 0)
            return 'R$ 0,00';
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }

}
//essa função é para casas decimais para tela de NFe
if (!function_exists('requestNumeroDecimal4DigitosOracle')) {

    function requestNumeroDecimal4DigitosOracle($valor)
    {
        if ($valor === '0' or $valor === 0) {
            return '0,0000';
        }
        return number_format($valor, 4, ',', '.');
    }

}

//essa função é para quando um número decimal for gravado no banco
if (!function_exists('requestPercentualOracle')) {

    function requestPercentualOracle($valor)
    {
        if ($valor === '0' or $valor === 0) {
            return '0,00 %';
        }
        $valor = (float) $valor;
        if (!str_contains($valor, '.')) {
            $valor = $valor . ',00';
        } else {
            $qdeCaracteres = strlen($valor);
            if ($qdeCaracteres === 3) {
                $valor = $valor . '0';
            }
        }
        $valor = str_replace('.', ',', $valor);

        return $valor . " %";
    }

}

//essa função é para quando um número decimal for gravado no banco
if (!function_exists('requestPercentualOracleSemDigitos')) {

    function requestPercentualOracleSemDigitos($valor)
    {
        $valor = (int) $valor;
        if ($valor === 0) {
            return '0 %';
        }
        return $valor . " %";
    }

}

//essa função é para quando um número decimal for gravado no banco
if (!function_exists('insertPercentualOracle')) {

    function insertPercentualOracle($valor)
    {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
        $valor = str_replace(' %', '', $valor);

        return floatVal($valor);
    }

}

use App\Estado;
use App\Cidade;
use App\Rua;
use App\Bairro;

if (!function_exists('buscaLatitudeLongitude')) {

    function buscaLatitudeLongitude($uf, $cidade, $bairro = null, $rua = null, $numero = '', $cep = null)
    {
        $estado = Estado::findOrFail($uf);
        $estado = $estado->descricao;
        $cidade = Cidade::findOrFail($cidade);
        $cidade = $cidade->descricao;
        if ($rua != null) {
            $rua = Rua::findOrFail($rua);
            $rua = $rua->descricao;
        } else {
            $rua = '';
        }
        if ($bairro != null) {
            $bairro = Bairro::findOrFail($bairro);
            $bairro = $bairro->descricao;
        } else {
            $bairro = '';
        }

        $enderecoCompleto = "$numero $rua, $bairro, $cidade, $estado,brasil";
        // codifica os parâmetros da url
        $enderecoCompleto = urlencode($enderecoCompleto);
        try {

            $config = \App\Empresaconfig::where('empresa_id', Session::get('empresa_padrao')->id)->select('keygooglemaps')->get()->first();

            $key = "";
            if (is_null($config) || (!is_null($config) && is_null($config->keygooglemaps))) {
                $config = \App\Configuracoesgerais::select('keygooglemaps')->first();
            }

            if(! is_null($config) && ! is_null($config->keygooglemaps)){
                $key = $config->keygooglemaps;
            }
            $urlBase = 'https://maps.google.com/maps/api/geocode/json?address=';
            $results = json_decode(file_get_contents($urlBase . $enderecoCompleto . '&sensor=false&key=' . $key))->results;

            if (count($results) == 0 || (count($results) > 0 && $results[0]->geometry->location_type == "GEOMETRIC_CENTER")) {
                $enderecoCompleto = ($cep === null ? "" : $cep . ", ") . $cidade . ',' . $estado . ',brasil';
                $enderecoCompleto = urlencode($enderecoCompleto);
                $results = json_decode(file_get_contents($urlBase . $enderecoCompleto . '&sensor=false&key=' . $key))->results;
                if (count($results) == 0) {
                    $enderecoCompleto = $cidade . ',' . $estado . ',brasil';
                    $enderecoCompleto = urlencode($enderecoCompleto);
                    $results = json_decode(file_get_contents($urlBase . $enderecoCompleto . '&sensor=false&key=' . $key))->results;
                }
            }

            if (count($results) > 0)
                return $results[0]->geometry;
        } catch (\Exception $e) {
            $results = (object) [];
            $results->location_type = 'not found';
            $results->location = (object) ['lat' => 0, 'lng' => 0];
            return $results;
        }
    }

}


if (!function_exists('converterLitrosAbastecimento')) {

    function converterBaseCalcOracle($valor)
    {
        return $valor = str_replace(',', '.', $valor);
    }

}

if (!function_exists('conversaoLitros')) {

    function conversaoLitros($valor)
    {
        if ($valor === '0' or $valor === 0) {
            return '0,000';
        }
        $valor = (float) $valor;
        if (!str_contains($valor, '.')) {
            return $valor . ',000';
        }
        $arraychar = explode($valor, ',');
        if (isset($arraychar[1])) {
            $qntachar = strlen($arraychar[1]);
            if ($qntachar === 1) {
                return $valor . ',00';
            } else if ($qntachar === 2) {
                return $valor . ',0';
            }
        }
        return $valor;
    }

}

if (!function_exists('converterLitrosBanco')) {

    function converterLitrosBanco($valor)
    {
        return $valor = str_replace(',', '.', $valor);
    }

}

if (!function_exists('conversaoPeso')) {

    function conversaoPeso($valor)
    {
        $valor = str_replace(' Kg', '', $valor);
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
        return $valor;
    }

}

if (!function_exists('requestPesoOracle')) {

    function requestPesoOracle($valor, $casas)
    {
        if ($valor === "0" || $valor === 0) {
            return '0,000 Kg';
        }
        $valor = str_replace('.', ',', $valor);
        $valor = strrev($valor);
        $qdeCaracteresSemVirgula = strlen(str_replace(',', '', $valor));
        if ($qdeCaracteresSemVirgula > 4) {
            if (!str_contains($valor, ',')) {
                $valor = '0,' . substr($valor, 0, 3) . substr($valor, 3, $qdeCaracteresSemVirgula);
            } else {
                $quebrarVirgula = explode(',', $valor);
                $quebrarVirgula[0] = strlen($quebrarVirgula[0]) === 1 ? '0' . $quebrarVirgula[0] : $quebrarVirgula[0];
                if (strlen($quebrarVirgula[1]) > 3) {
                    $valor = $quebrarVirgula[0] . ',' . substr($quebrarVirgula[1], 0, 3) . '.' . substr($quebrarVirgula[1], 3, $qdeCaracteresSemVirgula);
                } else {
                    $valor = $quebrarVirgula[0] . ',' . $quebrarVirgula[1];
                }
            }
        } else {
            if ((double) strrev($valor) < 0.999) {
                if ($qdeCaracteresSemVirgula === 1) {
                    $valor = '0' . $valor . '0';
                } else {
                    $valor = $valor . '0';
                }
            } else {
                $valor = '0,' . $valor;
            }
        }
        $valor = strrev($valor);
        $casasApos = isset(explode(',', $valor)[1]) ? strlen(explode(',', $valor)[1]) : 0;
        if ($casasApos < $casas)
            $valor = str_pad($valor, strlen($valor) - $casasApos + $casas, '0');
        return $valor . " Kg";
    }

}

if (!function_exists('requestPesoInteiroOracle')) {

    function requestPesoInteiroOracle($valor)
    {
        if ($valor === "0" || $valor === 0) {
            return '0 Kg';
        }
        return number_format($valor, 0, '', '.') . " Kg";
    }

}

if (!function_exists('requestBaseCalcNfOracle')) {

    function requestBaseCalcNfOracle($valor, $precision = 2)
    {
        if (floatVal($valor) === 0.0) {
            return str_pad('0,', 2 + $precision, '0');
        }

        $valor = number_format($valor, $precision, ',', '.');
        return $valor;
    }

}

if (!function_exists('insertTimeZone')) {

    function insertTimeZone($valor)
    {
        $explodetimezone = explode(":", $valor);
        $hora = $explodetimezone[0];
        $hora = str_replace('-', '', $hora);
        $valor = ($hora * 60);
        return $valor;
    }

}
if (!function_exists('nfMake')) {

    function nfMake($nf)
    {
        $imp = collect([]);
        for ($i = 0; $i < count($nf); $i++) {
            $n = (object) array();
            $n->codigo = strlen($nf[$i]->codigo) == 2 ? str_pad($nf[$i]->codigo, 3, '0', STR_PAD_LEFT) : $nf[$i]->codigo;
            $n->id = $nf[$i]->id;
            $n->descricao = $nf[$i]->descricao;
            $imp->push($n);
        }
        $imp = $imp->sortBy('codigo');
        $csts = collect([]);
        foreach ($imp as $cst) {
            $obj = (object) array();
            $codigo = substr($cst->codigo, 1, strlen($cst->codigo));
            $codigo = substr($cst->codigo, 0, 1) == 0 ? $codigo : $cst->codigo;
            $obj->des = $codigo . " " . $cst->descricao;
            $csts[$cst->id] = $obj->des;
        }
        return $csts->prepend("Selecione", "");
    }

}

if (!function_exists('makeCest')) {

    function makeCest($cest)
    {
        $cests = [];
        foreach ($cest as $cst) {
            $obj = (object) array();
            $obj->id = $cst->id;
            $obj->des = $cst->cest . "|" . $cst->descricao;
            $cests[$obj->id] = $obj->des;
        }
        return $cests;
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

if (!function_exists('maskCNPJ')) {

    function maskCNPJ(&$cnpj) {
        $cnpj = mask($cnpj, "##.###.###/####-##");
        return $cnpj;
    }

}

if (!function_exists('maskCPF')) {

    function maskCPF(&$cpf) {
        $cpf = mask($cpf, "###.###.###-##");
        return $cpf;
    }

}

if (!function_exists('corrigirVeiculo')) {

    function corrigirVeiculo($veiculos)
    {
        $veiculo = [];
        $veiculo[''] = 'Selecione';
        for ($i = 0; $i < count($veiculos); $i++) {
            $ve = (object) array();
            $ve->id = $veiculos[$i]->id;
            $ve->des = $veiculos[$i]->descricao . " - " . $veiculos[$i]->placa;
            $veiculo[$ve->id] = $ve->des;
        }
        return $veiculo;
    }

}

use App\Services\CarbonCustom as Carbon;

//função usada para saber quando será o proximo vencimento passando somente o dia
if (!function_exists('getProximoVencimento')) {

    function getProximoVencimento($diaVencimento, $diaFechamento = null)
    {
        $now = Carbon::now();
        $yearMonth = $now->year . "-" . $now->month;

        $diaVencimento = validateDaysOfMonth($now, $diaVencimento);

        $dateNext = Carbon::parse($yearMonth . "-" . $diaVencimento);
        if ($now->day >= $diaVencimento) {
            $dateNext->addMonth();
        }
        if ($diaFechamento) {
            $diaFechamento = validateDaysOfMonth($now, $diaFechamento);
            $fechamento = Carbon::parse($yearMonth . "-" . $diaFechamento);
            if ($now->day >= $diaFechamento) {
                $fechamento->addMonth();
            }

            if ($diaVencimento < $diaFechamento || $diaFechamento <= $now->day) {
                $dateNext->addMonth();
            }
        }

        return $dateNext->toDateString() . " 23:59:59";
    }

}

if (!function_exists('validateDaysOfMonth')) {

    function validateDaysOfMonth(Carbon $now, $dia)
    {
        $isMonth30days = $now->month === 4 || $now->month === 6 || $now->month === 9 || $now->month === 11;

        if ($isMonth30days && $dia > 30)
            $dia = 30;
        else if ($now->month === 2 && $dia > 28)
            $dia = 28;
        return $dia;
    }

}
if (!function_exists('getDescricaoMes')) {

    function getDescricaoMes($numeromes)
    {
        $numeromes = (int) $numeromes;

        switch ($numeromes) {
            case 1:
                return "Janeiro";
            case 2:
                return "Fevereiro";
            case 3:
                return "Março";
            case 4:
                return "Abril";
            case 5:
                return "Maio";
            case 6:
                return "Junho";
            case 7:
                return "Julho";
            case 8:
                return "Agosto";
            case 9:
                return "Setembro";
            case 10:
                return "Outubro";
            case 11:
                return "Novembro";
            default:
                return "Dezembro";
        }
    }

}

use App\Condicaopagamento;
use App\Condicaopagamentoparcela;

if (!function_exists('calculoParcelas')) {

    function calculoParcelas($id, $data, $valortotal, $condicaopagamento = null, $diaVencimento = null)
    {
        if (is_null($condicaopagamento))
            $condicaopagamento = Condicaopagamento::find($id);
        switch ($condicaopagamento->tipo) {
            case "2":
            case '0':
                $dinheiro = [];
                $valor = $valortotal;
                $dias = $condicaopagamento->dias_primeira;
                $dataven = $data->addDays($dias);
                $date = explode(' ', requestDataOracle($dataven));
                $datavencimento = $date[0];
                $vista = ["datavenc" => $datavencimento, "valor" => $valor];
                array_push($dinheiro, $vista);
                return $dinheiro;
            case "1":
                $parcelas = Condicaopagamentoparcela::where('condicaopagamento_id', $condicaopagamento->id)->get();
                $num = $condicaopagamento->num_parcelas;
                $total = $valortotal;
                $boletos = [];
                for ($i = 0; $i < $num; $i++) {
                    $dias = $parcelas[$i]->dias;
                    $dataven = $data->addDays($dias);
                    $date = explode(' ', requestDataOracle($dataven));
                    $datavencimento = $date[0];
                    $valor = $valortotal;
                    $valpar = ($valor * ($parcelas[$i]->percentualvalor / 100));
                    $totalult = $valor - $valpar;
                    if ($i === $num - 1) {
                        $valpar = $total - $totalult;
                    }
                    $boleto = ["datavenc" => $datavencimento, "valor" => $valpar];
                    array_push($boletos, $boleto);
                }
                return $boletos;
            case "4":
                $convenios = [];
                $datavencimento = requestDataOracle(getProximoVencimento($diaVencimento), false, true, false);
                $convenio = ["datavenc" => $datavencimento, "valor" => $valortotal];
                array_push($convenios, $convenio);
                return $convenios;
            default:
                $cartaoprazo = [];
                $par = $condicaopagamento->num_parcelas;
                $total = $valortotal;
                for ($i = 0; $i < $par; $i++) {
                    $dias = $condicaopagamento->dias_primeira;
                    $dataven = $data->addDays($dias);
                    $date = explode(' ', requestDataOracle($dataven));
                    $datavencimento = $date[0];
                    $valor = $valortotal;
                    $val = ($valor / $par);
                    $totalult = ($valor - $val);
                    if ($i === $par - 1) {
                        $val = $total - $totalult;
                    }
                    $prazocar = ["datavenc" => $datavencimento, "valor" => $val];
                    array_push($cartaoprazo, $prazocar);
                }
                return $cartaoprazo;
        }
    }

}

if (!function_exists('distanciaCoord')) {

    function distanciaCoord($lat1, $lon1, $lat2, $lon2)
    {
        $lat1 = deg2rad($lat1);
        $lat2 = deg2rad($lat2);
        $lon1 = deg2rad($lon1);
        $lon2 = deg2rad($lon2);

        $latD = $lat2 - $lat1;
        $lonD = $lon2 - $lon1;

        $dist = 2 * asin(sqrt(pow(sin($latD / 2), 2) +
                cos($lat1) * cos($lat2) * pow(sin($lonD / 2), 2)));
        $dist = ($dist * 6371) * 1000;
        return number_format($dist, 0, '.', '');
    }

}

if (!function_exists('getTimezone')) {

    function getTimezone($uf)
    {
        return collect(['AC' => 'America/Rio_branco',
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

if (!function_exists('setTimezone')) {

    function setTimezone()
    {
        try {
            $empresa = Session::get('empresa_padrao');
            if (!isset($empresa->uf) || (isset($empresa->uf) && empty($empresa->uf)))
                $timezone = config('app.timezone');
            else
                $timezone = getTimezone($empresa->uf);
            date_default_timezone_set($timezone);
        } catch (Exception $e) {

        }
    }
}

if (!function_exists('getErrorsException')) {

    function getErrorsException($e)
    {
        $error = $e->getMessage();

        if (env("APP_ENV") === "production") return $error;

        return $error .  " Line: " . $e->getLine() . " File: " . $e->getFile();
    }
}

if (!function_exists('isForeignKeyViolation')) {

    /**
     * Detecta violação de chave estrangeira ao excluir (registro filho existe).
     * Oracle: ORA-02292. PostgreSQL: SQLSTATE 23503 (foreign_key_violation).
     * Mantém compatibilidade com a mensagem Oracle legada durante a migração.
     */
    function isForeignKeyViolation($e)
    {
        $msg = $e->getMessage();
        if (strpos($msg, "23503") !== false) return true;       // PostgreSQL
        if (strpos($msg, "ORA-02292") !== false) return true;   // Oracle (legado)
        return false;
    }

}

if (!function_exists('sendMail')) {

    function sendMail(array $data)
    {
        $data['content'] = htmlspecialchars_decode($data['content']);

        Mail::send('layouts.mail', array('content' => $data['content']), function ($mail) use ($data) {
            $mail->to($data['to'])->subject($data['subject']);
            if (isset($data['files'])) {
                foreach ($data['files'] as $file) {
                    $mail->attach($file);
                }
            }
        });
    }

}

if (!function_exists('hasStrIn')) {

    function hasStrIn($str, array $values)
    {
        foreach ($values as $value) {
            if ($str === $value)
                return true;
        }
        return false;
    }

}

if (!function_exists('encodeAssociativeArray')) {

    /**
     * Encode a associative array to string
     * @param Array $array
     * @return string $str
     *
     */
    function encodeAssociativeArray($array)
    {
        $str = "[";
        foreach ($array as $key => $value) $str .= "\"$key\" => $value,|b ";
        $length = strlen($str);
        $str = substr($str, 0, $length - 4) . "]";
        return $str;
    }

}

if (!function_exists('decodeAssociativeArray')) {

    /**
     * Decode string created by encodeAssociativeArray() to string
     * @param string $str
     * @return Array $arr
     *
     */
    function decodeAssociativeArray($str)
    {
        $str = preg_replace("/\[/", "", $str);
        $str = preg_replace("/\]/", "", $str);

        $array = explode(",|b ", $str);
        $arr = [];
        foreach ($array as $value) {
            $value = explode(" => ", $value);
            $key = preg_replace("/\"/", "", $value[0]);

            $arr[$key] = $value[1];
        }

        return $arr;
    }

}

if (!function_exists('emptyToNull')) {

    /**
     * Transform values of array from empty to null
     *
     * @param  array $data
     * @return array $data
     */
    function emptyToNull($data)
    {
        if (!isset($data) && gettype($data) !== "array" && count($data) <= 0)
            return $data;

        foreach ($data as $key => $value) {
            $data[$key] = $data[$key] === "" ? null : $data[$key];
        }

        return $data;
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
     * @return string
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

if (!function_exists('paginate')) {

    /**
     *
     * @param \Illuminate\Support\Collection $collection
     * @param int $page
     * @param int $resultsPerPage
     * @return Collection
     */
    function paginate(\Illuminate\Support\Collection $collection, $page, $resultsPerPage)
    {
        $collection = $collection->chunk($resultsPerPage);
        $count = $collection->count();
        if ($count > 0) {
            for ($i = (int) $page; $i >= 0; $i--) {
                if ($collection->has($i)) {
                    $collection = $collection[$i];
                    break;
                }
            }
        }
        return $collection;
    }

}


if (!function_exists('saveLogoNfeStorage')) {

    /**
     *
     * @param base64|string $img
     * @param type $empresa
     * @return boolean
     */
    function saveLogoNfeStorage($img, $empresa)
    {
        $file = 'images\\' . str_replace(['/', '.', '-'], '', $empresa->cnpj) . '_logo.png';
        \Storage::disk('nfe')->put($file, base64_decode($img));
        return true;
    }

}

if (!function_exists('rawTranslateSpecialChars')) {

    /**
     * retorna uma string ou instancia de DB::raw() para pesquisas sem diferenciar acentos no oracle
     * @param string $column
     * @param bool $dbRaw
     * @return string or DB::raw();
     */
    function rawTranslateSpecialChars($column, $dbRaw = false)
    {
        $str = "lower(translate(lower($column), 'àáâãäåéèêëïìíîóòôöõúùûüýÿçñ', 'aaaaaaeeeeiiiiooooouuuuyycn'))";
        if ($dbRaw)
            return DB::raw($str);
        return $str;
    }

}

if (!function_exists('calcPercent')) {

    /**
     *
     * @param double $value
     * @return double
     */
    function calcPercent($value)
    {
        return $value / 100;
    }

}

if (!function_exists('getPathCertificateNFe')) {

    /**
     * @return string
     */
    function getPathCertificateNFe()
    {
        $s = DIRECTORY_SEPARATOR;
        return base_path("storage{$s}nfe{$s}certs{$s}");
    }

}

if (!function_exists('removeTransparencyImg')) {

    /**
     * passa a imagem e o caminho de destino
     * retornando true quando a imagem foi convertida e false quando não
     * @var $input_file
     * @var $output_file
     * @return type
     */
    function removeTransparencyImg($inputFile, $outputFile)
    {
        $input = imagecreatefrompng($inputFile);
        list($width, $height) = getimagesize($inputFile);
        $output = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($output, 255, 255, 255);
        imagefilledrectangle($output, 0, 0, $width, $height, $white);
        imagecopy($output, $input, 0, 0, 0, 0, $width, $height);
        return imagejpeg($output, $outputFile);
    }

}

if (!function_exists('customCryptLegacyBase64')) {
    // Implementação ANTIGA (apenas base64 repetido). Mantida só para conseguir
    // LER segredos gravados antes da correção. NÃO usar para gravar.
    function customCryptLegacyBase64($pass, $times = 0)
    {
        $cript = $pass;
        for ($i = 0; $i <= $times; $i++) {
            $cript = base64_encode($cript);
        }
        return $cript;
    }
    function customDecryptLegacyBase64($pass, $times = 0)
    {
        $cript = $pass;
        for ($i = 0; $i <= $times; $i++) {
            $cript = base64_decode($cript);
        }
        return $cript;
    }
}

if (!function_exists('customCrypt')) {

    // FASE 1 (segurança — S2): antes "criptografava" com base64 repetido, que é
    // REVERSÍVEL por qualquer um. Era usado para a senha do CERTIFICADO DIGITAL
    // A1 da NF-e e senha de e-mail. Agora usa criptografia real do Laravel
    // (Crypt::encrypt, AES-256 autenticado, chaveado pela APP_KEY).
    function customCrypt($pass, $times = 0)
    {
        if (is_null($pass) || $pass === '') {
            return $pass;
        }
        return \Illuminate\Support\Facades\Crypt::encrypt($pass);
    }

}

if (!function_exists('customDecrypt')) {

    // Lê o segredo. Tenta a criptografia nova (Crypt); se falhar, assume que é
    // um dado LEGADO em base64 e usa o decodificador antigo (retrocompatível).
    // Assim, segredos já gravados continuam legíveis e os novos ficam seguros.
    function customDecrypt($pass, $times = 0)
    {
        if (is_null($pass) || $pass === '') {
            return $pass;
        }
        try {
            return \Illuminate\Support\Facades\Crypt::decrypt($pass);
        } catch (\Exception $e) {
            // Fallback para o formato antigo (base64 repetido).
            return customDecryptLegacyBase64($pass, $times);
        }
    }

}

if (!function_exists('zipFiles')) {

    /**
     * @param $aFiles
     * @param $namezip
     * @param $msgException
     * @return object
     * @throws Exception
     */
    function zipFiles($aFiles, $namezip, $msgException)
    {

        ob_start();
        $z = new \ZipArchive();

        if (file_exists($namezip)) {
            $created = $z->open($namezip, \ZipArchive::OVERWRITE);
        } else {
            $created = $z->open($namezip, \ZipArchive::CREATE);
        }
        if ($created === true) {

            foreach ($aFiles as $file) {
                $z->addFromString($file['name'], $file['file']);
            }

            $filename = $z->filename;

            // Salvando o arquivo
            $z->close();
            return (object) [
                'filename' => $filename,
                'namezip'  => $namezip
            ];
        } else {
            throw new Exception($msgException);
        }
    }

}

if (! function_exists("keepRevisionsArr")) {
    /**
     * Method to compare and store various columns of the table to keep revisions of
     * creates one or more rows
     *
     * @param array $old
     * @param array $new
     * @param Illuminate\Database\Eloquent\Model $model eloquent model
     * @param string $identity column of the model to be identified
     * @return boolean
     */
    function keepRevisionsArr($old, $new, $model, $identity)
    {
        $revs = new \Venturecraft\Revisionable\Revision();
        $arr = collect([]);
        $changes = [];
        $changedto = [];
        $dontkeep = ["updated_at"];
        foreach ($old as $key => $antigo) {
            if (strpos($key, "data") !== false) {
                $new[$key] = $new[$key] . " 00:00:00";
            }
            if ((!isset($new[$key]) || $old[$key] != $new[$key]) && !in_array($key, $dontkeep)) {
                $changes[$key] = $old[$key];
                $changedto[$key] = $new[$key];
            }
        }
        foreach ($changes as $key => $value) {
            $revision = keepRevisions($changes[$key], $changedto[$key], $key, $model, $identity, false);
            $arr->push($revision);
        }
        $revs->insert($arr->toArray());
        return true;
    }

}

if (! function_exists("keepRevisions")) {
    /**
     * Method to make manual revision of tables
     *
     * @param string $old
     * @param string $new
     * @param string $key : chave o que ou qual campo representa na tabela a ser revisada
     * @param Illuminate\Database\Eloquent\Model $model eloquent model
     * @param boolean $now se deseja salvar agora ou retornar o objeto
     * @return void or
     * @return \Venturecraft\Revisionable\Revision $rev
     */
    function keepRevisions($old, $new, $key, $model, $identity, $now = true)
    {
        $user_id = \Auth::user()->getAuthIdentifier();
        $revision = new \Venturecraft\Revisionable\Revision();
        $revision->revisionable_type = $model->getMorphClass();
        $revision->revisionable_id = $model->getKey();
        $revision->key = $key;
        $revision->old_value = $old;
        $revision->new_value = $new;
        $revision->user_id = $user_id;
        $revision->created_at = Carbon::now();
        $revision->updated_at = Carbon::now();
        $revision->identity = $model->{$identity};
        $revision->identityBy = $identity;
        if ($now) {
            $saved = $revision->save();
            return true;
        } else {
            return $revision;
        }
    }

}

if (! function_exists("isArrayDiffMultidimensional")) {
    /**
     * @param array $arr1
     * @param array $arr2
     * @param bool $strict
     * @return bool
     */
    function isArrayDiffMultidimensional($arr1, $arr2, $strict = false)
    {
        $diff = false;
        foreach ($arr1 as $i => $nf) {
            if (! isset($arr2[$i])) {
                $diff = true;
            } else {
                $diff = isArrayDiff($nf, $arr2[$i], $strict);
            }
            if ($diff) {
                break;
            }
        }

        return $diff;
    }
}

if (! function_exists("isArrayDiff")) {
    /**
     * @param array $arr1
     * @param array $arr2
     * @param bool $strict
     * @return bool
     */
    function isArrayDiff($arr1, $arr2, $strict = false)
    {
        $diff = false;
        foreach ($arr1 as $i => $key) {
            if ($strict) {
                $diff = $diff || ($key !== $arr2[$i]);
            } else {
                $diff = $diff || ($key != $arr2[$i]);
            }
            if ($diff) {
                break;
            }
        }
        return $diff;
    }

    if (! function_exists("getEstados")) {
        /**
         * @return array
         */
        function getEstados()
        {
            return [
                "AC" => "Acre",
                "AL" => "Alagoas",
                "AM" => "Amazonas",
                "AP" => "Amapá",
                "BA" => "Bahia",
                "CE" => "Ceará",
                "DF" => "Distrito Federal",
                "ES" => "Espírito Santo",
                "GO" => "Goiás",
                "MA" => "Maranhão",
                "MG" => "Minas Gerais",
                "MS" => "Mato Grosso do Sul",
                "MT" => "Mato Grosso",
                "PA" => "Pará",
                "PB" => "Paraíba",
                "PE" => "Pernambuco",
                "PI" => "Piauí",
                "PR" => "Paraná",
                "RJ" => "Rio de Janeiro",
                "RN" => "Rio Grande do Norte",
                "RO" => "Rondônia",
                "RR" => "Roraima",
                "RS" => "Rio Grande do Sul",
                "SC" => "Santa Catarina",
                "SE" => "Sergipe",
                "SP" => "São Paulo",
                "TO" => "Tocantins"
            ];
        }
    }

    if (! function_exists("csvIbptDir")) {
        /**
         * @return string
         */
        function csvIbptDir()
        {
            $ds = DIRECTORY_SEPARATOR;
//            . 'ibpt' . $ds
            $path = storage_path('app' . $ds . 'public' . $ds. 'ibpt' . $ds);
            return $path;
        }
    }

    if (! function_exists("setMailConfig")) {

        /**
         * @param $request
         * @param bool $test
         * @return array|bool|object|string
         * @throws Exception
         */
        function setMailConfig($request, $test = true) {

            $econf = getConfigMail($request);

            if (! $econf) {
                return false;
            } else if (is_string($econf)) {
                return $econf;
            }

            $mailer = [
                'from' => ["address" => $econf->user, "name" => $econf->name],
                'driver' => 'smtp',
                'host' => $econf->host,
                'port' => $econf->port,
                'username' => $econf->user,
                'password' => $econf->pass,
                'encryption' => $econf->enc,
            ];
            if ($test) {
                $tes = ['stream' => [
                    // Certificado SSL auto-assinado, apenas para testes.
                    'ssl' => [
                        'allow_self_signed' => true,
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ],
                ]];
                $mailer = array_merge($mailer, $tes);
            }
            return $mailer;
        }
    }

    if (! function_exists("setMailConfigApi")) {

        function setMailConfigApi()
        {

            $econf = getConfigMailApi();

            if (! $econf) {
                return false;
            } else if (is_string($econf)) {
                return $econf;
            }

            $mailer = [
                'from' => ["address" => $econf->user, "name" => $econf->name],
                'driver' => 'smtp',
                'host' => $econf->host,
                'port' => $econf->port,
                'username' => $econf->user,
                'password' => $econf->pass,
                'encryption' => $econf->enc,
            ];
            return $mailer;
        }
    }

    if (! function_exists("getConfigMail")) {

        /**
         * @param $request
         * @return object|string
         * @throws Exception
         */
        function getConfigMail($request) {

            $info = (object) $request->all();
            $objconf = (object) array();

            $fromC = isset($info->config) && $info->config == "true";

            if (isset($info->configGeneral)) {
                $econf = $info->configGeneral;
            } elseif (isset($info->empresa_id_config) && $info->empresa_id_config) {
                $econf = \DB::table('empresaconfigs')
                    ->where('empresa_id', $info->empresa_id_config)
                    ->get()
                    ->first();
            } else {
                $econf = Session::get('empresa_config');
            }

            $pass = $fromC && $info->password ? $info->password : customDecrypt($econf->emailsenha, 8);

            if ($fromC) {
                $objconf->host = $info->host;
                $objconf->port = $info->port;
                $objconf->name = $info->name;
                $objconf->user = $info->username;
                $objconf->pass = $pass;
            } else if ($econf->emailremetente) {
                $objconf->host = $econf->emailservidorsmtp;
                $objconf->port = $econf->emailportasmtp;
                $objconf->name = $econf->emailnomeremente;
                $objconf->user = $econf->emailremetente;
                $objconf->pass = $pass;
            } else {
                throw new \Exception('Não foi possível encontrar as configurações da empresa');
            }

            if ($objconf->port === "465") {
                $objconf->enc = "ssl";
            } else if ($objconf->port === "587") {
                $objconf->enc = "tls";
            } else {
                $objconf = "criptografia errada";
            }
            return $objconf;
        }
    }

    if (! function_exists("getConfigMailApi")) {

        /**
         * @param $request
         * @return object|string
         * @throws Exception
         */
        function getConfigMailApi()
        {

            $objconf = (object) array();
            $pass = customDecryptMail(env("MAIL_PASSWORD"));
            $objconf->host = env("MAIL_HOST");
            $objconf->port = env("MAIL_PORT");
            $objconf->name = "Gás em Casa";
            $objconf->user = env("MAIL_USERNAME");
            $objconf->pass = $pass;

            if ($objconf->port === "465") {
                $objconf->enc = "ssl";
            } else if ($objconf->port === "587") {
                $objconf->enc = "tls";
            } else {
                $objconf = "criptografia errada";
            }
            return $objconf;
        }
    }

    if (! function_exists("customDecryptMail")) {
        function customDecryptMail($password)
        {
            return str_replace("R-P-I-N-F-O%2019", "", str_replace("Q-T-I%2019", "", base64_decode($password)));
        }
    }

    if (! function_exists("customCryptMail")) {
        function customCryptMail($password)
        {
            return base64_encode("R-P-I-N-F-O%2019" . $password . "Q-T-I%2019");
        }
    }

    if (! function_exists("buildPath")) {

        /**
         * @param array $args
         * @return string
         * @throws Exception
         */
        function buildPath(array $args)
        {
            $path = "";
            $i = 0;
            foreach ($args as $arg) {
                if (strlen($arg) === 0) {
                    throw new Exception(
                        "Não foi possível gerar o caminho para armazenar arquivos no servidor: " .
                        "O argumento " . $i . " foi passado vazio."
                    );
                }
                $i++;
                $path .= $arg . DIRECTORY_SEPARATOR;
            }
            return $path;
        }

    }

    if (! function_exists("onlyNumbers")) {

        /**
         * @param string $string
         * @return bool
         */
        function onlyNumbers(string $string)
        {
            return preg_replace('/[^0-9]/s', '', is_string($string) ? $string : "");
        }
    }

    if (! function_exists("satLog")) {

        /**
         * @param string $message
         */
        function satLog(string $message)
        {
            $disk = \Storage::disk('sat');
            $now = Carbon::now();
            $filename = $now->format("dmY") . ".log";
            $message = $now->toDateTimeString() . " | " . $message . PHP_EOL;
            if (! $disk->has($filename)) {
                $disk->put($filename, $message);
            } else {
                $disk->append($filename, $message);
            }
        }
    }
}

if (! function_exists("hashClientSecret"))
{
    function hashClientSecret($pass)
    {
        return base64_encode(hash_hmac('sha256', $pass, 'secret', true));
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
        return response()->json([
            'data'      => $data,
            'msg'       => utf8FormatJson($msg),
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
        return response()->json([
            'msg'       => utf8FormatJson($msg),
            'status'    => "NOK"
        ], $status);
    }
}

if (! function_exists('responseReject')) {
    /**
     * @param $msg
     * @param int $status
     * @return \Illuminate\Http\JsonResponse
     */
    function responseReject($msg, $status = 200)
    {
        return response()->json([
            'msg'       => utf8FormatJson($msg),
            'status'    => "OPS"
        ], $status);
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

if (! function_exists("strEncode")) {

    /**
     * @param $val
     * @return null|string|string[]
     */
    function strEncode($val)
    {
        return mb_convert_encoding($val, 'UTF-8', 'UTF-8');
    }
}

if (! function_exists('getGMapsLatLgn')) {

    /**
     * @param $uf
     * @param $cidade
     * @param $bairro
     * @param $rua
     * @param string $numero
     * @param null $key
     * @param null $cep
     * @return mixed|object
     */
    function getGMapsLatLgn($uf, $cidade, $bairro, $rua, $numero = '', $key = null, $cep = null)
    {
        try {

            $enderecoCompleto = "$numero $rua, $bairro, $cidade, $uf,brasil";
            if (! $uf || ! $cidade || ! $bairro || ! $rua) {
                throw new Exception("Endereço informado é incorreto: " . $enderecoCompleto);
            }
            $getByCep = function () use ($cep, $cidade, $uf, $key) {
                $results = getGMapsResults($key, urlencode("$cep, $cidade, $uf,brasil"));
                $count = count($results);
                if ($count > 0) {
                    if ($results[0]->geometry->location_type == "RANGE_INTERPOLATED") {
                        throw new Exception(
                            "O endereço encontrado não foi preciso, tente verificar as informações de endereço da revenda incluindo o CEP."
                        );
                    }
                    return $results[0]->geometry;
                } else {
                    throw new Exception(
                        "Não foi possível buscar a latitude e longitude da revenda verifique o endereço informado incluindo o CEP."
                    );
                }
            };

            $results = getGMapsResults($key, urlencode($enderecoCompleto));
            $count = count($results);
            if ($count > 0) {
                if ($results[0]->geometry->location_type == "RANGE_INTERPOLATED") {
                    if (! $cep) {
                        throw new Exception(
                            "O endereço encontrado não foi preciso, tente verificar as informações de endereço da revenda."
                        );
                    } else {
                        return call_user_func($getByCep);
                    }
                }
                return $results[0]->geometry;
            } else {
                if (! $cep) {
                    throw new Exception(
                        "Não foi possível buscar a latitude e longitude da revenda verifique o endereço informado."
                    );
                } else {
                    return call_user_func($getByCep);
                }
            }
        } catch (Exception $e) {
            $results = (object)[];
            $results->location_type = 'not found';
            $results->error = $e->getMessage();
            $results->location = (object) ['lat' => 0, 'lng' => 0];
            return $results;
        }
    }
}

if (! function_exists('getGMapsResults')) {

    /**
     * @param $key
     * @param $enderecoCompleto
     * @return mixed
     * @throws Exception
     */
    function getGMapsResults($key, $enderecoCompleto)
    {
        if (! $key) {
            throw new Exception("Chave do Google Maps não informada.");
        }

        $urlBase = 'https://maps.google.com/maps/api/geocode/json?address=';
        return json_decode(file_get_contents($urlBase . $enderecoCompleto . '&sensor=false&key=' . $key))->results;

    }

}


if (! function_exists('internalResponseError')) {

    /**
     * @param \Exception|string $ex
     * @return array
     */
    function internalResponseError($ex, $code = 500)
    {
        $msg = "";
        if ($ex instanceof \Exception) {
            $msg = $ex->getMessage();
        } else {
            $msg = $ex;
        }

        return [
            'msg'       => utf8FormatJson($msg),
            'status'    => 'NOK',
            'code'      => $code
        ];
    }

}

if (! function_exists('internalResponseSuccess')) {

    /**
     * @param array $data
     * @param string $msg
     * @return array
     */
    function internalResponseSuccess($data, $msg)
    {
        return [
            'data'      => $data,
            'msg'       => utf8FormatJson($msg),
            'status'    => 'OK',
            'code'      => 200
        ];
    }

}

if (! function_exists('getInputOrFail')) {
    /**
     * @param $key
     * @param null $default
     * @param bool $msg
     * @return mixed
     * @throws Exception
     */
    function getInputOrFail($key, $default = null, $msg = false)
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

if (! function_exists('now')) {
    /**
     * Create a new Carbon instance for the current time.
     *
     * @param  \DateTimeZone|string|null $tz
     * @return \Illuminate\Support\Carbon
     */
    function now($tz = null)
    {
        return Carbon::now($tz);
    }
}

if (! function_exists('encodeSecret')){
    function encodeSecret($txt)
    {
        $secret = base64_encode(hash_hmac('sha256', $txt, 'secret', true));
        return $secret;
    }
}

if (! function_exists('gerarCodigoAleatorio')){
    function gerarCodigoAleatorio() {

        $chars = "abcdefghijkmnopqrstuvwxyz023456789";
        srand((double)microtime()*1000000);
        $i = 0;
        $pass = '' ;

        while ($i <= 7) {
            $num = rand() % 33;
            $tmp = substr($chars, $num, 1);
            $pass = $pass . $tmp;
            $i++;
        }

        return strtoupper($pass);

    }
}

if (! function_exists("makeObs")) {
    /**
     * Método que pega o cliente e cria observacoes para o pedido
     */
    function makeObs($cliente, $justObs = false)
    {
        $isEmpty = $cliente->clienteProduto->isEmpty();

        if ($isEmpty && !$justObs) return collect($cliente);

        if ($isEmpty && $justObs) return "";

        $baseObs = $cliente->observacoes;
        $obs = "";
        $lenth = $cliente->clienteProduto->count();
        $i = 0;
        $tipos = [
            1 => "Todos",
            2 => "Aplicativo",
            3 => "Disk"
        ];

        foreach ($cliente->clienteProduto as $cliprod) {
            $dataNego = Carbon::createFromFormat("Y-m-d H:i:s", $cliprod->created_at)->format("d/m/y");
            $produto = $cliprod->produto->descricao;
            $tipo = "";
            $value = 0;

            if ($cliprod->tipo == 1) {
                $isFixo = $cliprod->desconto == 0;
                $value = $isFixo ? $cliprod->preco : $cliprod->desconto;
                $value = requestNumeroDecimalOracle($value);

                $tipo = $isFixo ? "Fixo" : "Desconto";
            } else {
                $value = requestPercentualOracle($cliprod->desconto * 100);
                $tipo = "Percentual";
            }
            $para = "Para: " . $tipos[$cliprod->descontopara];
            $obs .= "$produto: $tipo $value em $dataNego $para";
            $i++;

            if ($i != $lenth) $obs .= ", ";
        }

        $observacoes = "$obs $baseObs";

        if ($justObs) return $observacoes;

        $cliente->observacoes = $observacoes;

        return collect($cliente);
    }
}

if (! function_exists("guid4")) {
    /**
     * Generates UUID v4 based on RFC-4122
     *
     * @param string|null data
     * @return string $uuid
     */
    function guid4($data = null)
    {
        $data = $data ?? random_bytes(16);

        if (strlen($data) !== 16) throw new \Exception("GUIDv4: String need to be of length 16.");

        // ? Set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // ? Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        // ? Output the 36 character UUID.
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (!function_exists("throwIf")) {
    function throwIf($condition, $message, $code = 0) {
        if ($condition) {
            throw new Exception($message, $code);
        }
    }
}
