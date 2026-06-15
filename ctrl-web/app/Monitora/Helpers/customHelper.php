<?php
if (!function_exists('imageResize')) {
	function imageResize($file, $w, $h, $crop = FALSE)
	{
		$percent = 0.08;
		list($width, $height, $type) = getimagesize($file);
		$newwidth = $width * $percent;
		$newheight = $height * $percent;
		if ($type === 1) $source = imagecreatefromgif($file);
		if ($type === 2) $source = imagecreatefromjpeg($file);
		if ($type === 3) $source = imagecreatefrompng($file);
		$thumb = imagecreatetruecolor($newwidth, $newheight);
		imagecopyresized($thumb, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
		if ($type === 1) $source = imagegif($thumb, $file, 100);
		if ($type === 2) $source = imagejpeg($thumb, $file, 100);
		if ($type === 3) $source = imagepng($thumb, $file, 100);
		return $thumb;
	}
	// function imageResize($file, $w, $h) {
	//    list($width, $height, $tipo) = getimagesize($file);
	//    if ($tipo===1) $src = imagecreatefromjpeg($file);
	//    if ($tipo===2) $src = imagecreatefromgif($file);
	//    if ($tipo===3) $src = imagecreatefrompng($file);
	//   //  $src = imagecreatefromjpeg($file);
	//    $dst = imagecreatetruecolor($w, $h);
	//    imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $width, $height);
	// if ($type===1) $source = imagegif($thumb, $file, 100);
	// if ($type===2) $source = imagejpeg($thumb, $file, 100);
	// if ($type===3) $source = imagepng($thumb, $file, 100);
	//    return $dst;
	// }
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
					$dataSaida .= ' ' . $hr[0] . ":" . $hr[1];
			}
		}
		return $dataSaida;
	}
}
if (!function_exists('requestDataOracle')) {

	function requestDataOracle($dataEntrada, $hora = true, $fullYear = true)
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
						$dataSaida .= ' ' . $hr[0] . ":" . $hr[1] . ":" . $hr[2];
				}
			}
		}
		return $dataSaida;
	}
}
if (!function_exists('requestDataOracleSemHora')) {

	function requestDataOracleSemHora($dataEntrada, $hora = true)
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
		return $valor;
	}
}
//essa função é para quando um número decimal for gravado no banco
if (!function_exists('requestNumeroDecimalOracle')) {

	function requestNumeroDecimalOracle($valor)
	{
		if ($valor === '0' or $valor === 0) {
			return 'R$ 0,00';
		}
		return 'R$ ' . number_format($valor, 2, ',', '.');
		//        $valor = str_replace('.', ',', $valor);
		//        $valor = strrev($valor);
		//        $qdeCaracteresSemVirgula = strlen(str_replace(',', '', $valor));
		//        if ($qdeCaracteresSemVirgula > 3) {
		//            if (!str_contains($valor, ',')) {
		//                $valor = '00,' . substr($valor, 0, 3) . '.' . substr($valor, 3, $qdeCaracteresSemVirgula);
		//            } else {
		//                $quebraVirgula = explode(',', $valor);
		//                $quebraVirgula[0] = strlen($quebraVirgula[0]) === 1 ? '0' . $quebraVirgula[0] : $quebraVirgula[0];
		//                if (strlen($quebraVirgula[1]) > 3) {
		//                    $valor = $quebraVirgula[0] . ',' . substr($quebraVirgula[1], 0, 3) . '.' . substr($quebraVirgula[1], 3, $qdeCaracteresSemVirgula);
		//                } else {
		//                    $valor = $quebraVirgula[0] . ',' . $quebraVirgula[1];
		//                }
		//            }
		//        } else {
		//            if ((double) strrev($valor) < 0.99) {
		//                if ($qdeCaracteresSemVirgula === 1) {
		//                    $valor = '0' . $valor . '0';
		//                } else {
		//                    $valor = $valor . '0';
		//                }
		//            } else {
		//                $valor = '00,' . $valor;
		//            }
		//        }
		//        $valor = strrev($valor);
		//        return "R$ " . $valor;
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
if (!function_exists('insertPercentualOracle')) {

	function insertPercentualOracle($valor)
	{
		$valor = str_replace('.', '', $valor);
		$valor = str_replace(',', '.', $valor);
		$valor = str_replace(' %', '', $valor);
		return $valor;
	}
}

use App\Estado;
use App\Cidade;
use App\Rua;
use App\Bairro;

if (!function_exists('buscaLatitudeLongitude')) {

	function buscaLatitudeLongitude($uf, $cidade, $bairro = null, $rua = null, $numero = '')
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
		$enderecoCompleto = "$numero " . $rua . ',' . $bairro . ',' . $cidade . ',' . $estado . ',brasil';
		// codifica os dados para a url funcionar
		$enderecoCompleto = urlencode($enderecoCompleto);
		$results = json_decode(file_get_contents('http://maps.google.com/maps/api/geocode/json?address=' . $enderecoCompleto . '&sensor=false'))->results;
		if (count($results) == 0) {
			$enderecoCompleto = $cidade . ',' . $estado . ',brasil';
			$enderecoCompleto = urlencode($enderecoCompleto);
			$results = json_decode(file_get_contents('http://maps.google.com/maps/api/geocode/json?address=' . $enderecoCompleto . '&sensor=false'))->results;
		}
		if (count($results) > 0)
			return $results[0]->geometry;
		$results = (object) [];
		$results->location_type = 'not found';
		$results->location = (object) ['lat' => 0, 'lng' => 0];
		return $results;
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

	function requestPesoOracle($valor)
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
			if ((float) strrev($valor) < 0.999) {
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

	function requestBaseCalcNfOracle($valor)
	{
		if ($valor === '0' or $valor === 0) {
			return '0,00';
		}
		$valor = (float) $valor;

		$valor = strrev($valor);
		if (!str_contains($valor, '.')) {
			$valor = '00,' . $valor;
		} else {
			$valorexplode = explode('.', $valor);
			$qdeCaracteres = strlen($valorexplode[0]);
			if ($qdeCaracteres === 1) {
				$valor = '0' . $valor;
			}
		}
		$valor = strrev(str_replace('.', ',', $valor));

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

use Carbon\Carbon;

//função usada para saber quando será o proximo vencimento passando somente o dia
if (!function_exists('getProximoVencimento')) {
	function getProximoVencimento($dia)
	{
		if (strlen($dia) == 1) {
			$dia = '0' . $dia;
		}
		if (explode(' ', explode('-', Carbon::now('America/Sao_Paulo'))[2])[0] >= $dia) {
			$mes = explode('-', Carbon::now('America/Sao_Paulo'))[1] + 1;
			$mes = $mes == 13 ? '01' : $mes;
			$ano = $mes == 13 ? explode('-', Carbon::now('America/Sao_Paulo'))[0] + 1 : explode('-', Carbon::now('America/Sao_Paulo'))[0];
		} else {
			$ano = explode('-', Carbon::now('America/Sao_Paulo'))[0];
			$mes = explode('-', Carbon::now('America/Sao_Paulo'))[1];
		}
		switch ($mes) {
			case 4 || 6 || 9 || 11:
				if ($dia > 30)
					$dia = 30;
				break;
			case 2:
				if ($dia > 28)
					$dia = 28;
				break;
		}

		if (strlen($mes) == 1)
			$mes = '0' . $mes;

		$mesAno = $ano . '-' . $mes;
		$dataCompleta = $mesAno . '-' . $dia . ' 23:59:59';
		return $dataCompleta;
	}
}

if (!function_exists('calculoParcelas')) {

	function getDescricaoMes($numeromes)
	{
		$numeromes = (int) $numeromes;

		switch ($numeromes) {
			case 1:
				return "Janeiro";
				break;
			case 2:
				return "Fevereiro";
				break;
			case 3:
				return "Março";
				break;
			case 4:
				return "Abril";
				break;
			case 5:
				return "Maio";
				break;
			case 6:
				return "Junho";
				break;
			case 7:
				return "Julho";
				break;
			case 8:
				return "Agosto";
				break;
			case 9:
				return "Setembro";
				break;
			case 10:
				return "Outubro";
				break;
			case 11:
				return "Novembro";
				break;
			default:
				return "Dezembro";
				break;
		}
	}
}

if (!function_exists('buscarDadosRastreamento')) {
	function buscarDadosRastreamento($url, $empresa_id = null)
	{
		$client = new \GuzzleHttp\Client(['base_uri' => Session::get('config')->urlsistemaweb . '/public/api/']);

		$query = array('user' => \Auth::guard('monitora')->user()->email);
		if ($empresa_id != null) {
			$query = array('user' => \Auth::guard('monitora')->user()->email, 'empresa_id' => $empresa_id);
		}
		$headers = [
			'Authorization' => 'Bearer ' . \Auth::guard('monitora')->user()->access_token,
			'Accept'        => 'application/json',
		];
		$response = $client->request('POST', $url, [
			'headers' => $headers,
			['debug'   => true],
			'query' => $query
		])->getBody()->getContents();
		return $response;
	}
}

if (!function_exists('buscarAccessToken')) {

	function buscarAccessToken($password, $username, $scope, $grant_type, $client_id, $secret = "")
	{
		$client = new \GuzzleHttp\Client(['base_uri' => Session::get('config')->urlsistemaweb . '/public/oauth/']);
		$query = array(
			'username' => $username,
			'password' => $password,
			'client_secret' => empty($secret) ? encodeSecret($password) : $secret,
			'scope' => $scope,
			'grant_type' => $grant_type,
			'client_id' => $client_id
		);
		$headers = [
			'content_type' => 'application/x-www-form-urlencoded'
		];
		$response = $client->request('POST', 'token', [
			'headers' => $headers,
			'form_params' => $query,
			'processData' => false,
			'contentType' => false,
		])->getBody()->getContents();
		$retorno = GuzzleHttp\json_decode($response);
		return $retorno->access_token;
	}
}

if (!function_exists('encodeSecret')) {
	function encodeSecret($txt)
	{
		// FASE 1 (segurança): a chave de assinatura era o literal 'secret' (S7),
		// permitindo forjar o secret a quem conhecesse o código. Agora usa a
		// APP_KEY da aplicação (única por ambiente, fora do código).
		$key = env('SECRET_HMAC_KEY', config('app.key'));
		$secret = base64_encode(hash_hmac('sha256', $txt, $key, true));
		return $secret;
	}
}

if (!function_exists('buscarDadosTraccar')) {
	function buscarDadosTraccar($url, $query)
	{
		$client = new \GuzzleHttp\Client(['base_uri' => Session::get('config')->urltraccar . '/api/']);
		$credentials = base64_encode(Session::get('config')->usertraccar . ':' . Session::get('config')->passwordtraccar);
		$headers = [
			'Authorization' => "Basic " . $credentials,
			'Accept'        => 'application/json',
		];
		$response = $client->request('GET', $url, [
			'headers' => $headers,
			['debug'   => true],
			'query' => $query
		])->getBody()->getContents();
		return $response;
	}
}
