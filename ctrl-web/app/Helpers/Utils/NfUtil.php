<?php

namespace App\Helpers\Utils;

use App\Empresa;
use App\Financeirorateio;
use App\Nfemitida;
use App\Nfemitidaitem;
use App\Nfrecebida;
use App\Nfrecebidaitem;
use App\Nfrecebidaparcela;
use App\Services\CarbonCustom;
use DB;
use Illuminate\Support\Collection;
use Input;
use Session;
use \Exception;
use App\Nfoperacao;
use App\Planoconta;
use App\Nfemitidaparcela;
use App\Financeiroparcela;
use App\Repository\SelectRepository as Repository;
use NFePHP\NFe\Factories\Contingency;
use App\Services\CarbonCustom as Carbon;
use \Venturecraft\Revisionable\Revision;

/**
 * Contém funções úteis usadas para Nota Fiscal
 *
 * @author Jeferson
 */
final class NfUtil
{

    private static $tiponf;

    /**
     * Retorna um Array dos Modelos
     * @return array
     */
    public static function getNfModelos()
    {
        return [
            55 => '55 - NF-e',
            65 => '65 - NFC-e'
        ];
    }

    /**
     * @param $cProdANP
     * @return bool
     */
    public static function isCodAnpComb($cProdANP)
    {
        return in_array($cProdANP, static::getCProdANPGLP());
    }

    /**
     * Retorna um Array com os Tipos de Ambiente
     * @return array
     */
    public static function getNfTipoAmbiente()
    {
        return [
            1 => '1 - Produção',
            2 => '2 - Homologação'
        ];
    }

    /**
     * Retorna um Array com as Finalidades
     * @return array
     */
    public static function getNfFinalidade()
    {
        return [
            1 => '1 - Normal',
            2 => '2 - Complementar',
            3 => '3 - NFe de Ajuste',
            4 => '4 - Devolução de Mercadoria'
        ];
    }

    /**
     * Retorna um Array com os tipos de presença do comprador
     * @return array
     */
    public static function getPrecencaComprador()
    {
        return [
            0 => '0 - Não se aplica (ex: NF complementar ou de ajuste)',
            1 => '1 - Operação Presencial',
            2 => '2 - Operação Não Presencial, pela internet',
            3 => '3 - Operação Não Presencial, teleatendimento',
            4 => '4 - NFC-e em operação com entrega em domicílio',
            5 => '5 - Operação não presencial, fora do estabelecimento',
            9 => '9 - Operação não presencial, outros'
        ];
    }

    /**
     * Retorna um Array com os tipos de fretes
     * @return array
     */
    public static function getFreteModalidades()
    {
        return [
            0 => '0 - Contratação do Frete por conta do Remetente (CIF)',
            1 => '1 - Contratação do Frete por conta do Destinatário (FOB)',
            2 => '2 - Contratação do Frete por conta de Terceiros',
            3 => '3 - Transporte Próprio por conta do Remetente',
            4 => '4 - Transporte Próprio por conta do Destinatário',
            9 => '9 - Sem Ocorrência de Transporte'
        ];
    }

    /**
     * Retorna um Array com os Tipos de pagamento
     * @return array
     */
    public static function getNfFormasPagamento()
    {
        return [
            0 => '0 - À vista',
            1 => '1 - À prazo',
            2 => '2 - Outros'
        ];
    }

    /**
     * valida os campos de plano de conta para sped
     *
     * @param array $data
     * @return array
     */
    private static function validateDataPC($data)
    {
        if (isset($data['planoconta_id']) && $data['planoconta_id'] != "") {
            $planoconta_id = $data['planoconta_id'];

            $planoconta = Planoconta::findOrFail($planoconta_id);
            $data['planocontadescricao'] = $planoconta->descricao;
            $data['naturezasped'] = $planoconta->naturezasped;
            $data['planocontadata'] = $planoconta->created_at;
            if ($planoconta->updated_at != null)
                $data['planocontadata'] = $planoconta->updated_at;
        } elseif (isset($data["rateios"]) && $data["rateios"] != null) { // Substitui o rateio default pelos informados
            $rateios = is_null(json_decode($data["rateios"])) ? [] : json_decode($data["rateios"]);
            foreach ($rateios as $rateio) {
                $planoconta_id = $rateio[3];

                $planoconta = Planoconta::findOrFail($planoconta_id);
                $data['planocontadescricao'] = $planoconta->descricao;
                $data['naturezasped'] = $planoconta->naturezasped;
                $data['planocontadata'] = $planoconta->created_at;
                if ($planoconta->updated_at != null) {
                    $data['planocontadata'] = $planoconta->updated_at;
                    break;
                }
            }
        }

        return $data;
    }

    /**
     * valida e trata os dados vindos da view
     *
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function validateDataGeneral($data)
    {
        $empresa = Session::get('empresa_padrao');
        $data['empresa_id'] = $empresa->id;

        $data['user_id'] = \Auth::user()->id;
        $data['grupo_id'] = $empresa->grupo_id;

        $nfOperacao = Nfoperacao::findOrFail($data["nfoperacao_id"]);
        $data['tipo'] = $nfOperacao->tiponf;
        $data['cfop'] = $nfOperacao->cfop;
        $data['descricaooperacao'] = $nfOperacao->descricao;

        //0-emissão de NF-e com aplicativo do contribuinte;
        //1-emissão de NF-e avulsa pelo Fisco;
        //2-emissão de NF-e avulsa, pelo contribuinte com seu certificado digital, através do site do Fisco;
        //3-emissão NF-e pelo contribuinte com aplicativo fornecido pelo Fisco;
        $data['nfprocessoemissao'] = 0;
        $data['nfversaoprocessamento'] = 1;
        $data['datahoraemissao'] = insertDataOracle($data["datahoraemissao"]);
        $data['datahoraentradasaida'] = insertDataOracle($data["datahoraentradasaida"]);

        $data['vprod'] = strpos($data['vprod'], ',') === false ? $data['vprod'] : insertNumeroDecimalOracle($data['vprod']);
        $data['vfrete'] = strpos($data['vfrete'], ',') === false ? $data['vfrete'] : insertNumeroDecimalOracle($data['vfrete']);
        $data['vbruto'] = strpos($data['vbruto'], ',') === false ? $data['vbruto'] : insertNumeroDecimalOracle($data['vbruto']);
        $data['vdesc'] = strpos($data['vdesc'], ',') === false ? $data['vdesc'] : insertNumeroDecimalOracle($data['vdesc']);
        $data['vnf'] = strpos($data['vnf'], ',') === false ? $data['vnf'] : insertNumeroDecimalOracle($data['vnf']);
        $data['vpis'] = strpos($data['vpis'], ',') === false ? $data['vpis'] : insertNumeroDecimalOracle($data['vpis']);
        $data['vcofins'] = strpos($data['vcofins'], ',') === false ? $data['vcofins'] : insertNumeroDecimalOracle($data['vcofins']);
        $data['vst'] = strpos($data['vst'], ',') === false ? $data['vst'] : insertNumeroDecimalOracle($data['vst']);
        $data['vbcst'] = strpos($data['vbcst'], ',') === false ? $data['vbcst'] : insertNumeroDecimalOracle($data['vbcst']);
        $data['vicms'] = strpos($data['vicms'], ',') === false ? $data['vicms'] : insertNumeroDecimalOracle($data['vicms']);
        $data['vbc'] = strpos($data['vbc'], ',') === false ? $data['vbc'] : insertNumeroDecimalOracle($data['vbc']);
        $data['vseg'] = strpos($data['vseg'], ',') === false ? $data['vseg'] : insertNumeroDecimalOracle($data['vseg']);
        $data['voutro'] = strpos($data['voutro'], ',') === false ? $data['voutro'] : insertNumeroDecimalOracle($data['voutro']);
        $data['vipi'] = strpos($data['vipi'], ',') === false ? $data['vipi'] : insertNumeroDecimalOracle($data['vipi']);
        $data['vicmsdeson'] = strpos($data['vicmsdeson'], ',') === false ? $data['vicmsdeson'] : insertNumeroDecimalOracle($data['vicmsdeson']);
        $data['vfcp'] = strpos($data['vfcp'], ',') === false ? $data['vfcp'] : insertNumeroDecimalOracle($data['vfcp']);
        $data['vfcpst'] = strpos($data['vfcpst'], ',') === false ? $data['vfcpst'] : insertNumeroDecimalOracle($data['vfcpst']);

        if (isset($data['setor_id']))
            unset($data['setor_id']);

        //CAMPOS TRATADOS PARA INSERIR
        $data['emitcpf'] = str_replace(['-', '.'], '', $data['emitcpf']);
        $data['emitcnpj'] = str_replace(['-', '/', '.'], '', $data['emitcnpj']);
        $data['emitcep'] = str_replace('-', '', $data['emitcep']);
        $data['emittelefone'] = str_replace(["(", ")", " ", "-"], '', $data['emittelefone']);
        $data['emitie'] = str_replace(['.', '-'], '', $data['emitie']);

        $data['fretecpf'] = str_replace(['-', '.'], '', $data['fretecpf']);
        $data['fretecnpj'] = str_replace(['-', '/', '.'], '', $data['fretecnpj']);
        $data['freteplaca'] = str_replace('-', '', $data['freteplaca']);

        $data['destcpf'] = str_replace(['.', '-'], '', $data['destcpf']);
        $data['destcnpj'] = str_replace(['-', '/', '.'], '', $data['destcnpj']);
        $data['destcep'] = str_replace('-', '', $data['destcep']);
        $data['desttelefone'] = str_replace(["(", ")", " ", "-"], '', $data['desttelefone']);
        $data['destie'] = str_replace(['.', '-'], '', $data['destie']);
        $data['nitem'] = count(json_decode($data['produtos']));

        $nfmodelo = $data['nfmodelo'];

        $data["indfinal"] = isset($data["indfinal"]) ? $data["indfinal"] : "0";

        if ($nfmodelo == 55) {
            $data['nftipoambiente'] = $empresa->nfetipoambiente;
        } else if ($nfmodelo == 65) {
            $data["indfinal"] = "1";
            $data['nftipoambiente'] = $empresa->nfcetipoambiente;
        }

        $data['produtosJson'] = $data['produtos'];
        $data['produtosjson'] = $data['produtos'];

        return emptyToNull(self::validateDataPC($data));
    }

    /**
     *
     * @param int $financeiro_id
     * @return \Illuminate\Support\Collection
     */
    public static function getFinanceiroParcela($financeiro_id)
    {
        return Financeiroparcela::where('financeiro_id', $financeiro_id)->get();
    }

    /**
     *
     *
     * @param int $nf_id
     * @param string $type
     * @return \Illuminate\Support\Collection
     */
    public static function getFinanceiroNf($nf_id, $type = 'emitida')
    {
        if ($type === "emitida") {
            $parc = Nfemitidaparcela::where('nfemitida_id', $nf_id)->get();
        } else {
            $parc = Nfrecebidaparcela::where('nfrecebida_id', $nf_id)->get();
        }

        foreach ($parc as $par) {
            $par->valorefetivado = $par->valororiginal;
        }

        return $parc;
    }

    /**
     * @param $nf
     * @param Nfemitidaitem|Nfrecebidaitem $nfitems
     * @return mixed
     */
    public static function adjustValuesToForm($nf, $nfitems)
    {
        $nf->vbc = requestNumeroDecimalOracle($nf->vbc);
        $nf->vbruto = requestNumeroDecimalOracle($nf->vnf + $nf->vdesc - $nf->vfrete);
        $nf->liquidoParcelas = requestNumeroDecimalOracle($nf->vnf - $nf->vfrete);
        $nf->vicmsdeson = requestNumeroDecimalOracle($nf->vicmsdeson);
        $nf->vfrete = requestNumeroDecimalOracle($nf->vfrete);
        $nf->totalvfrete = $nf->vfrete;
        $nf->vnf = requestNumeroDecimalOracle($nf->vnf);
        $nf->vprod = requestNumeroDecimalOracle($nf->vprod);
        $nf->vseg = requestNumeroDecimalOracle($nf->vseg);
        $nf->vicms = requestNumeroDecimalOracle($nf->vicms);
        $nf->vbcst = requestNumeroDecimalOracle($nf->vbcst);
        $nf->vst = requestNumeroDecimalOracle($nf->vst);
        $nf->vcofins = requestNumeroDecimalOracle($nf->vcofins);
        $nf->vpis = requestNumeroDecimalOracle($nf->vpis);
        $nf->totalvdesc = requestNumeroDecimalOracle($nf->vdesc);
        $nf->vdesc = requestNumeroDecimalOracle($nf->vdesc);
        $nf->vipi = requestNumeroDecimalOracle($nf->vipi);
        $nf->voutro = requestNumeroDecimalOracle($nf->voutro);
        $nf->vfcp = requestNumeroDecimalOracle($nf->vfcp);
        $nf->vfcpst = requestNumeroDecimalOracle($nf->vfcpst);
        $nf->totalqtdeprodutos = $nfitems->sum('qcom');
        $nf->totalpesobruto = number_format($nfitems->sum('pesob'), 3, ',', '.') . " Kg";
        $nf->totalpesoliquido = number_format($nfitems->sum('pesol'), 3, ',', '.') . " Kg";

        $nf->datahoraemissao = requestDataOracle($nf->datahoraemissao);
        $nf->datahoraentradasaida = requestDataOracle($nf->datahoraentradasaida);
        return $nf;
    }

    /**
     * validando o tamanho do campo descricaooperacao em bytes
     * @param array $data
     * @throws Exception
     */
    public static function validateCustom($data)
    {
        $len = mb_strlen($data['descricaooperacao']);
        if ($len > 60) {
            throw new Exception('O campo Operação possui ' . $len . ' caracteres e não pode ser maior que 60 caracteres.');
        }

        $fin = $data['nfefinalidade'] == 4 || $data['nfefinalidade'] == 2;
        $cfop = $data['cfop'] == "5929";

        if ($cfop && $data["nfmodelo"] != "55") {
            throw new Exception("CFOP 5929 pode ser utilizada somente com modelo 55!");
        }

        if ($fin || $cfop) {
            $chaveacessoref = trim($data['chaveacessoref']);
            if ($chaveacessoref == "") {
                throw new Exception("É preciso informar a Chave de Referência para NF de finalidade 4 ou finalidade 2 ou CFOP 5929");
            }

            if ($cfop) {
                static::checkChaveExistance($chaveacessoref);
            }
        }
    }

    /**
     * formata os valores decimais para colocar no xml
     * @param $obj
     * @param int $precision
     * @param null $exceptions
     * @return mixed
     */
    public static function formatObjValues($obj, $precision = 2, $exceptions = null)
    {
        $originalPrecision = $precision;
        foreach ($obj as $key => $value) {
            if (is_array($exceptions) && array_key_exists($key, $exceptions)) {
                if (is_array($exceptions[$key]) && array_key_exists('precision', $exceptions[$key])) {
                    $precision = $exceptions[$key]['precision'];
                }
            }
            $obj->{$key} = strpos($value, ".") !== false ? sprintf("%0.{$precision}f", $value) : $value;
            $precision = $originalPrecision;
        }
        return $obj;
    }

    /**
     * @param $cnpj
     * @param $ambiente
     * @param $modelo
     * @param $chave
     * @param string $middlePath
     * @return bool|string
     * @throws Exception
     */
    public static function getXmlFile($cnpj, $ambiente, $modelo, $chave, $middlePath = "base", $contents = true)
    {
        $file = self::getXmlPath($cnpj, $ambiente, $modelo, $chave, $middlePath, true);

        if (!file_exists($file)) {
            $file = storage_path('nfe') . DIRECTORY_SEPARATOR . 'xml' . DIRECTORY_SEPARATOR .
                'nfes' . DIRECTORY_SEPARATOR . $ambiente . DIRECTORY_SEPARATOR . $middlePath .
                DIRECTORY_SEPARATOR . "{$chave}-nfe.xml"; //antigo diretório
        }
        if (file_exists($file))
            return $contents ? file_get_contents($file) : $file;
        else
            throw new Exception("Nao foi possível encontrar o arquivo do XML Gerado: $file", 404);
    }

    /**
     * @param $cnpj
     * @param $xml
     * @param $modelo
     * @param $ambiente
     * @param $filename
     * @param string $middlePath
     * @throws Exception
     */
    public static function saveXML($cnpj, $xml, $modelo, $ambiente, $filename, $middlePath = "base")
    {
        if (strlen($filename) === 0) {
            throw new Exception("Nome de arquivo não encontrado para armazenar o XML");
        }

        $file = static::getXmlPath($cnpj, $ambiente, $modelo, $filename, $middlePath);
        \Storage::disk('nfe')->put($file, $xml);
    }

    /**
     * @param $cnpj
     * @param $ambiente
     * @param $modelo
     * @param $filename
     * @param string $middlePath
     * @param bool $completePath
     * @return string
     * @throws Exception
     */
    public static function getXmlPath($cnpj, $ambiente, $modelo, $filename, $middlePath = "base", $completePath = false)
    {
        $basePath = $completePath ? storage_path('nfe') . DIRECTORY_SEPARATOR : '';

        $path = $basePath . buildPath([onlyNumbers($cnpj), $ambiente, $modelo, $middlePath]) . "{$filename}-nfe.xml";

        return $path;
    }

    /**
     * @param $chave
     * @param $cnpj
     * @param string $type
     * @param bool $completePath
     * @return string
     */
    public static function getPDFPath($chave, $cnpj, $type = "danfe", $completePath = true)
    {
        $base = storage_path('nfe') . DIRECTORY_SEPARATOR . "pdf" . DIRECTORY_SEPARATOR . onlyNumbers($cnpj) . DIRECTORY_SEPARATOR;
        if (!$completePath) {
            return $base;
        }
        return $base . $chave . "-{$type}.pdf";
    }

    /**
     * @param $cnpj
     * @return string
     */
    public static function getLogoPath($cnpj)
    {
        return storage_path('nfe' . DIRECTORY_SEPARATOR . "images") . DIRECTORY_SEPARATOR . "{$cnpj}_logo";
    }

    /**
     * @param null|Nfemitida|Nfrecebida $nf
     * @param null|string $cnpj
     * @param null|int|string $nfmodelo
     * @param null|Empresa|Collection $empresa
     * @return false|string
     * @throws Exception
     */
    public static function getConfigTools($nf = null, $cnpj = null, $nfmodelo = null, $empresa = null)
    {
        if ($nf !== null) {
            $cnpj = $nf->emitcnpj;
            $nfmodelo = (int) $nf->nfmodelo;
            $empresa = $nf->empresa;
        } elseif ($cnpj === null || $nfmodelo === null || $empresa === null) {
            throw new Exception("Impossível gerar configurações para comunicação com o Sefaz.");
        }

        $tpAmb = (int) ($nfmodelo == 55 ? $empresa->nfetipoambiente : $empresa->nfcetipoambiente);
        if ($empresa->nfcetipoambiente == 1) {
            $csc = $empresa->nfcetoken_prod;
            $csc_id = $empresa->nfcetokenid_prod;
        } else {
            $csc = $empresa->nfcetoken;
            $csc_id = $empresa->nfcetokenid;
        }
        $msg = "O campo :field não foi cadastrado, verifique o cadastro da Empresa!";

        if (strlen($csc) === 0) {
            throw new Exception(str_replace(":field", "CSC", $msg));
        } elseif (strlen($csc_id) === 0) {
            throw new Exception(str_replace(":field", "Token ID", $msg));
        }

        $aConfig = array(
            'atualizacao' => date('Y-m-d h:i:s'),
            'tpAmb'       => $tpAmb,
            'schemes'     => 'PL_008i2',
            'razaosocial' => $empresa->razao_social,
            'siglaUF'     => $empresa->uf,
            'cnpj'        => $cnpj,
            "versao"      => NFE_VERSION,
            'tokenIBPT'   => '',
            "CSC"         => $csc,
            "CSCid"       => $csc_id,
            'aProxyConf'  => [
                'proxyIp'   => '',
                'proxyPort' => '',
                'proxyUser' => '',
                'proxyPass' => ''
            ]
        );

        return json_encode($aConfig);
    }


    /**
     * @param $std
     * @return array
     */
    public static function toArrayIdxInteger($std)
    {
        $arr = [];
        foreach ($std as $key => $value) {
            $arr[(int) $key] = $value;
        }
        return $arr;
    }

    /**
     * @param $ambiente
     * @return string
     */
    public static function getAmbiente($ambiente)
    {
        return $ambiente == 1 ? 'producao' : 'homologacao';
    }

    /**
     * calcula o valor do imposto com base na base de cálculo e alíquota
     * @param float $vBC
     * @param float $aliq
     * @param int $precision
     * @return float
     */
    public static function calcImpostoProp($vBC, $aliq, $precision = 2)
    {
        return floatVal(sprintf("%0.{$precision}f", $vBC * calcPercent($aliq)));
    }

    /**
     * @param Exception $e
     * @param bool $getTrace
     * @return string
     */
    public static function treatNFeException($e, $getTrace = false)
    {
        $m = str_replace("{http://www.portalfiscal.inf.br/nfe}", '', $e->getMessage());
        $m = str_replace("This XML is not valid.", "XML inválido:", $m);
        $m = str_replace("Element", "Tag", $m);
        $m = str_replace("[facet 'pattern'] ", "", $m);
        $m = str_replace("The value", "O valor", $m);
        $m = str_replace("is not accepted by the pattern", "não é aceito pelo padrão", $m);
        $m = str_replace("This element is not expected", "Este elemento não é esperado", $m);
        $m = str_replace("Expected is", "O esperado é", $m);
        $m = str_replace("is not a valid value of the atomic type", "Não é um valor do tipo", $m);
        $m = str_replace("Missing child element(s)", "Esperando elemento(s) filho(s)", $m);
        $m = str_replace("one of (", "um de (", $m);
        if ($getTrace)
            $originalContent = $e->getTraceAsString();
        else
            $originalContent = $e->getMessage();
        return $m . " Original content: " . $originalContent;
    }

    /**
     * @return array
     * @throws Exception
     */
    public static function getMotDesonICMS()
    {
        return [
            0 => [
                'cst'      => ['20', '70', '90'],
                'elements' => self::getMotDeson('ICMS20_70_90')
            ],
            1 => [
                'cst'      => ['30'],
                'elements' => self::getMotDeson('ICMS30')
            ],
            2 => [
                'cst'      => ['40', '41', '50'],
                'elements' => self::getMotDeson('ICMS40_41_50')
            ],
        ];
    }

    /**
     * @param $group
     * @return array
     * @throws Exception
     */
    private static function getMotDeson($group)
    {
        switch ($group) {
            case 'ICMS40_41_50':
                return [
                    1  => '1 - Táxi',
                    3  => '3 - Produtor Agropecuário',
                    4  => '4 - Frotista/Locadora',
                    5  => '5 - Diplomático/Consular',
                    6  => '6 - Utilitários e Motocicletas da Amazônia Ocidental e Áreas de Livre Comércio',
                    7  => '7 - SUFRAMA',
                    8  => '8 - Venda a Órgão Público',
                    9  => '9 - Outros',
                    10 => '10 - Deficiente Condutor',
                    11 => '11 - Deficiente Não Condutor',
                    16 => '16 - Olimpíadas Rio 2016'
                ];
            case 'ICMS30':
                return [
                    6 => ' 6 - Utilitários e Motocicletas da Amazônia Ocidental e Áreas de Livre Comércio',
                    7 => ' 7 - SUFRAMA',
                    9 => ' 9 - Outros'
                ];
            case 'ICMS20_70_90':
                return [
                    3  => '3 - Uso na agropecuária',
                    9  => '9 - Outros',
                    12 => '12 - Órgão de fomento e desenvolvimento agropecuário'
                ];
            default:
                $msg = "Grupo de ICMS não encontrado para buscar os motivos de Desoneração do ICMS";
                throw new Exception($msg);
        }
    }

    /**
     * @param null $cst
     * @param bool $getArray
     * @return array|bool
     */
    public static function useST($cst = null, $getArray = false)
    {
        $aValidate = ['900', '500', '203', '202', '201', '90', '70', '60', '30', '10'];
        if ($getArray)
            return $aValidate;
        else
            return in_array($cst, $aValidate);
    }

    /**
     * @param null $cst
     * @param bool $getArray
     * @return array|bool
     */
    public static function useICMS($cst = null, $getArray = false)
    {
        $aValidate = ['102', '103', '300', '400', '202', '203', '201', '900', '500', '90', '70', '51', '20', '10', '00'];
        if ($getArray)
            return $aValidate;
        else
            return !in_array($cst, $aValidate);
    }

    /**
     * @param null $cst
     * @param bool $getArray
     * @return array|bool
     */
    public static function useICMSUFDest($cst = null, $getArray = false)
    {
        $aValidate = ['40', '41', '103', '300', '400'];
        if ($getArray)
            return $aValidate;
        else
            return !in_array($cst, $aValidate);
    }

    /**
     * @param $cst
     * @param $ide
     * @param $cfop
     * @param $cProdANP
     * @param $indIEDest
     * @return bool
     */
    public static function generateICMSUFDest($cst, $ide, $cfop, $cProdANP, $indIEDest)
    {
        if (is_array($ide)) {
            $ide = (object) $ide;
        }
        return static::useICMSUFDest($cst)
            && (int) $ide->indFinal     === 1
            && (int) $ide->idDest       === 2
            && (int) $indIEDest         === 9
            && (int) $ide->mod          === 55
            && (int) $ide->tpNF         !== 0
            && (static::isCFOPComb($cfop) && in_array($cProdANP, static::getCProdANPGLP()));
    }

    /**
     * @param null $cst
     * @param bool $getArray
     * @return array|bool
     */
    public static function isSN($cst = null, $getArray = false)
    {
        $aValidate = ['101', '102', '103', '300', '400', '201', '202', '203', '500', '900'];
        if ($getArray)
            return $aValidate;
        else
            return in_array($cst, $aValidate);
    }

    /**
     * @param null $cst
     * @param bool $getArray
     * @return array|bool
     */
    public static function useDeson($cst = null, $getArray = false)
    {
        $aValidate = ['20', '30', '40', '41', '50', '70', '90'];
        if ($getArray)
            return $aValidate;
        else
            return in_array($cst, $aValidate);
    }

    /**
     * @param null $cst
     * @param bool $getArray
     * @return array|bool
     */
    public static function useDesonGroup40($cst = null, $getArray = false)
    {
        $aValidate = ['40', '41', '50'];
        if ($getArray)
            return $aValidate;
        else
            return in_array($cst, $aValidate);
    }

    /**
     * @param null $cst
     * @param bool $getArray
     * @return array|bool
     */
    public static function useFCP($cst = null, $getArray = false)
    {
        $aValidate = ['00', '10', '20', '30', '51', '60', '70', '90'];
        if ($getArray)
            return $aValidate;
        else
            return in_array($cst, $aValidate);
    }

    /**
     * @param null $cst
     * @param bool $getArray
     * @return array|bool
     */
    public static function useFCPNormal($cst = null, $getArray = false)
    {
        $aValidate = ['00', '10', '20', '51', '60', '70', '90'];
        if ($getArray)
            return $aValidate;
        else
            return in_array($cst, $aValidate);
    }

    /**
     * @param null $cst
     * @param bool $getArray
     * @return array|bool
     */
    public static function useFCPST($cst = null, $getArray = false)
    {
        $aValidate = ['10', '30', '60', '70', '90', '201', '202', '203', '500', '900'];
        if ($getArray)
            return $aValidate;
        else
            return in_array($cst, $aValidate);
    }

    /**
     * @param null $cst
     * @param bool $getArray
     * @return array|bool
     */
    public static function useREDBC($cst = null, $getArray = false)
    {
        $aValidate = ['20', '51', '70', '90', '900'];
        if ($getArray)
            return $aValidate;
        else
            return in_array($cst, $aValidate);
    }

    /**
     * @param null $cst
     * @param bool $getArray
     * @return array|bool
     */
    public static function useREDBCST($cst = null, $getArray = false)
    {
        $aValidate = ['10', '30', '70', '90', '201', '202', '203', '900'];
        if ($getArray)
            return $aValidate;
        else
            return in_array($cst, $aValidate);
    }

    /**
     * @param null $cst
     * @param bool $getArray
     * @return array|bool
     */
    public static function useTagPart($cst = null, $getArray = false)
    {
        $aValidate = ['10', '90'];
        if ($getArray)
            return $aValidate;
        else
            return in_array($cst, $aValidate);
    }

    /**
     * @param null $cst
     * @param bool $getArray
     * @return array|bool
     */
    public static function useAliqPISCOFINSPercent($cst = null, $getArray = false)
    {
        $aValidate = ['99', '03'];
        if ($getArray)
            return $aValidate;
        else
            return !in_array($cst, $aValidate);
    }

    /**
     * @param null $cst
     * @param bool $getArray
     * @return array|bool
     */
    public static function useDiferimento($cst = null, $getArray = false)
    {
        $aValidate = ['51'];
        if ($getArray)
            return $aValidate;
        else
            return in_array($cst, $aValidate);
    }

    /**
     * @param null $cst
     * @param bool $getArray
     * @return array|bool
     */
    public static function useMODBC($cst = null, $getArray = false)
    {
        $aValidate = ['00', '10', '20', '51', '70', '90', '900'];
        if ($getArray)
            return $aValidate;
        else
            return !in_array($cst, $aValidate);
    }

    /**
     * @param null $cst
     * @param bool $getArray
     * @return array|bool
     */
    public static function useMODBCST($cst = null, $getArray = false)
    {
        $aValidate = ['10', '30', '70', '90', '900', '201', '202', '203', '900'];
        if ($getArray)
            return $aValidate;
        else
            return !in_array($cst, $aValidate);
    }

    /**
     * @return array
     */
    public static function getCProdAnpGLP()
    {
        return [
            "210203001", "320101001", "320101002", "320102002", "320102001",
            "320102003", "320102005", "320201001", "320103001", "220102001",
            "320301001", "320103002", "820101032", "820101026", "820101027",
            "820101004", "820101005", "820101022", "820101031", "820101030",
            "820101014", "820101006", "820101016", "820101015", "820101025",
            "820101017", "820101018", "820101019", "820101020", "820101021",
            "420105001", "420101005", "420101004", "420102005", "420102004",
            "420104001", "820101033", "820101034", "420106001", "820101011",
            "820101003", "820101013", "820101012", "420106002", "830101001",
            "420301004", "420202001", "420301001", "420301002", "410103001",
            "410101001", "410102001", "430101004", "510101001", "510101002",
            "510102001", "510102002", "510201001", "510201003", "510301003",
            "510103001", "510301001"
        ];
    }

    /**
     * @param $type
     * @param $id
     * @return string
     */
    public static function getUrlPostWrite($type, $id)
    {
        return "/nf{$type}/" . $id . '?index=' . str_replace('&', "extPar", Input::get('index', route("nf{$type}.index")));
    }

    /**
     * @param $cst
     * @return string
     */
    public static function getTagIPI($cst)
    {
        if (in_array($cst, ['00', '49', '50', '99'])) {
            return "IPITrib";
        } else {
            return "IPINT";
        }
    }

    /**
     * @param $cst
     * @return string
     */
    public static function getTagPIS($cst)
    {
        return "PIS" . self::getTagPISCofins($cst);
    }

    /**
     * @param $cst
     * @return string
     */
    public static function getTagCofins($cst)
    {
        return "COFINS" . self::getTagPISCofins($cst);
    }

    /**
     * @param $cst
     * @return string
     */
    private static function getTagPISCofins($cst)
    {

        $aPISAliq = ['01', '02'];

        $aPISQtde = ['03'];

        $aPISNT = ['04', '05', '06', '07', '08', '09'];

        $aPISPOutr = [
            '49', '50', '51', '52', '53', '54', '55', '56', '60', '61', '62', '63',
            '64', '65', '66', '67', '70', '71', '72', '73', '74', '75', '98', '99'
        ];

        if (in_array($cst, $aPISAliq)) {
            return "Aliq";
        } elseif (in_array($cst, $aPISQtde)) {
            return "Qtde";
        } elseif (in_array($cst, $aPISNT)) {
            return "NT";
        } elseif (in_array($cst, $aPISPOutr)) {
            return "Outr";
        }
        return '';
    }

    /**
     * @param $cst
     * @return string
     */
    public static function getTagICMS($cst)
    {
        switch ($cst) {
            case '00':
                return "ICMS00";
            case '10':
                return "ICMS10";
            case '20':
                return "ICMS20";
            case '30':
                return "ICMS30";
            case '40':
                return "ICMS40";
            case '41':
                return "ICMS40";
            case '50':
                return "ICMS40";
            case '51':
                return "ICMS51";
            case '60':
                return "ICMS60";
            case '61':
                return "ICMS61";
            case '70':
                return "ICMS70";
            case '90':
                return "ICMS90";
            case '101':
                return "ICMSSN101";
            case '102':
                return "ICMSSN102";
            case '103':
                return "ICMSSN102";
            case '300':
                return "ICMSSN102";
            case '400':
                return "ICMSSN102";
            case '201':
                return "ICMSSN201";
            case '202':
                return "ICMSSN202";
            case '203':
                return "ICMSSN202";
            case '500':
                return "ICMSSN500";
            case '900':
                return "ICMSSN900";
            default:
                return ' ';
        }
    }

    /**
     * @param $obj
     * @return mixed
     */
    public static function nullToZero($obj)
    {
        foreach ($obj as $key => $value) {
            if (is_null($value))
                $obj->$key = 0;
        }
        return $obj;
    }

    /**
     * @param $nf
     * @return string
     * @throws Exception
     */
    public static function contingency($nf)
    {
        $contingency = new Contingency();

        $acronym = $nf->emituf;
        $motive = $nf->empresa->contingenciajustificativa;

        $type = self::getTypeContingency($nf);

        $status = $contingency->activate($acronym, $motive, $type);

        \Storage::disk('nfe')->put($nf->emitcnpj . DIRECTORY_SEPARATOR . 'contingency.txt', $status);
        return $status;
    }

    /**
     * @param $nf
     * @param bool $ignoreException
     * @return bool|string
     * @throws Exception
     */
    public static function getTypeContingency($nf, $ignoreException = false)
    {
        if ($nf->nfmodelo == '55') {
            switch ((int) $nf->empresa->nfetipoemissao) {
                case 4:
                    return "EPEC";
                case 6:
                    return "SVCAN";
                case 7:
                    return "SVCRS";
                default:
                    if ($ignoreException) {
                        return false;
                    } else {
                        throw new Exception("Erro ao verificar o tipo de emissão em contingência");
                    }
            }
        } else {
            switch ((int) $nf->empresa->nfcetipoemissao) {
                case 4:
                    return "EPEC";
                case 9:
                    return "OFF-LINE";
                default:
                    if ($ignoreException) {
                        return false;
                    } else {
                        throw new Exception("Erro ao verificar o tipo de emissão em contingência");
                    }
            }
        }
    }

    /**
     * @return array
     */
    public static function getCodSitCanc()
    {
        return [101, 151, 218, 135];
    }

    /**
     * @return array
     */
    public static function getCodSitDeneg()
    {
        return [110, 301, 302, 303];
    }

    /**
     * @return array
     */
    public static function getCodSitValid()
    {
        return [100, 135];
    }

    /**
     * @return array
     */
    public static function getCodSitInut()
    {
        return [102];
    }

    /**
     * @param $cStat
     * @return bool
     */
    public static function isAuthorized($cStat)
    {
        return in_array($cStat, [100, 101, 102, 110, 135, 150, 206]);
    }

    /**
     * @param $cStat
     * @return bool
     */
    public static function isDuplicated($cStat)
    {
        return in_array($cStat, [680, 681, 682, 684, 573, 344, 539, 485, 204]);
    }

    /**
     * @param $cStat
     * @return bool
     */
    public static function isCanceled($cStat)
    {
        return in_array($cStat, static::getCodSitCanc());
    }

    /**
     * @param $cStat
     * @return bool
     */
    public static function isInutilized($cStat)
    {
        return in_array($cStat, static::getCodSitInut());
    }

    /**
     * @param $cStat
     * @return bool
     */
    public static function isDeny($cStat)
    {
        return in_array($cStat, static::getCodSitDeneg());
    }

    /**
     * @param $cStat
     * @return bool
     */
    public static function isValid($cStat)
    {
        return in_array($cStat, static::getCodSitValid());
    }

    #region Revisions
    /**
     * Compares and creates rows for revisions of items
     *
     * @param \Illuminate\Database\Eloquent\Model    $model
     * @param array $data
     * @param string $index : index do array data que contem os produtos
     * @param string $key String com a descricao da coluna key
     * @return bool|Revision
     */
    public static function revisionItems($model, $data, $index, $key)
    {
        $old = self::getItemsRevisions($model);
        if (isset($data[$index]) && $data[$index]) {
            $new = json_decode($data[$index]);
        } else {
            return false;
        }

        $old_it = [];
        foreach ($old as $antigo) {
            $quantidade = requestNumeroDecimal4DigitosOracle($antigo->quantidade);
            $valor = requestNumeroDecimalOracle($antigo->valor);
            $old_it[] = "Nfoperacao_id: $antigo->operacao" .
                ", Setor_id: $antigo->setor" .
                ", Produto_id: $antigo->produto_id" .
                ", Quantidade: $quantidade" .
                ", Valor Unitario: $valor";
        }
        $new_it = [];
        foreach ($new as $novo) {
            $new_it[] = "Nfoperacao_id: " . $novo[0] .
                ", Setor_id: " . $novo[2] .
                ", Produto_id: " . $novo[4] .
                ", Quantidade: " . $novo[7] .
                ", Valor Unitario: " . $novo[6];
        }
        $velho = implode(' | ', $old_it);
        $novo  = implode(' | ', $new_it);
        $diff = $novo !== $velho;
        if ($diff) {
            return self::createRevisions($velho, $novo, $key, $model);
        }
        return true;
    }

    /**
     * Returns an array of items
     *
     * @param \Illuminate\Database\Eloquent\Model    $model
     * @return array $items
     */
    private static function getItemsRevisions($model)
    {
        if ($model->getMorphClass() == "App\Nfemitida") {
            $items = "nfemitidaitems";
            $id = "nfemitida_id";
        } else {
            $items = "nfrecebidaitems";
            $id = "nfrecebida_id";
        }
        $items = DB::table("$items")
            ->whereRaw("$id = $model->id")
            ->select(
                'nfoperacao_id as operacao',
                'setor_id as setor',
                'cprod as produto_id',
                'qcom as quantidade',
                'vuncom as valor'
            )
            ->get();
        return $items;
    }

    /**
     * Returns an array of rateios
     *
     * @param \Illuminate\Database\Eloquent\Model    $model
     * @return array $items
     */
    private static function getRateiosRevisions($model)
    {
        $items = collect([]);
        if (isset($model->financeiro_id)) {
            $items = DB::table("financeirorateios")
                ->whereRaw("financeiro_id = $model->financeiro_id")
                ->select('planoconta_id', 'centrocusto_id', 'valor')
                ->orderBy('planoconta_id')
                ->get();
        }
        return $items;
    }

    /**
     * Insert items on Revisions table
     *
     * @param string $old
     * @param string $new
     * @param string $key : Representa o campo key da tabela revisions que indica o que ele representa
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param boolean $now : valida se gera uma unica vez ou varias
     * @return bool|Revision
     */
    private static function createRevisions($old, $new, $key, $model, $now = true)
    {
        $user_id = \Auth::user()->getAuthIdentifier();
        $revision = new Revision();
        $revision->revisionable_type = $model->getMorphClass();
        $revision->revisionable_id = $model->getKey();
        $revision->key = $key;
        $revision->old_value = $old;
        $revision->new_value = $new;
        $revision->user_id = $user_id;
        $revision->identity = $model->empresa_id;
        $revision->identityBy = "empresa_id";
        $revision->created_at = Carbon::now();
        $revision->updated_at = Carbon::now();
        if ($now) {
            $revision->save();
            return true;
        }
        return $revision;
    }
    #endregion

    /**
     * @return array
     */
    public static function getSituacaoLancDoc()
    {
        return [
            "100" => "Normal",
            "101" => "Cancelado",
            //            "102" => "Inutilizado"
        ];
    }

    /**
     * @param $financeiro_id
     * @return bool
     */
    public static function allowedEstornoParcela($financeiro_id)
    {
        $pars = Repository::getFinanceiroAllowedCanc($financeiro_id);

        if (is_null($pars))
            return true;
        else
            return is_null($pars->cheque) && is_null($pars->ec) && is_null($pars->ecr) && is_null($pars->eccheque) && is_null($pars->chequee) && is_null($pars->bol);
    }

    /**
     * @return array
     */
    public static function getCFOPGLP()
    {
        return [
            '1651', '1652', '1653', '1657', '1658', '1659', '1660', '1661',
            '1662', '1663', '1664', '2651', '2652', '2653', '2658', '2659',
            '2660', '2661', '2662', '2663', '2664', '3651', '3652', '3653',
            '5651', '5652', '5653', '5654', '5655', '5656', '5657', '5658',
            '5659', '5660', '5661', '5662', '5663', '5664', '5665', '5666',
            '5667', '6651', '6652', '6653', '6654', '6655', '6656', '6657',
            '6658', '6659', '6660', '6661', '6662', '6663', '6664', '6665',
            '6666', '6667', '7651', '7654', '7667'
        ];
    }

    /**
     * cfops de vendas
     *
     * @updated_at 13/08/2018
     * @author Jeferson
     * @return array
     */
    public static function getCfopVenda()
    {
        return collect([
            '5101', '5102', '5103', '5104', '5105', '5106', '5109', '5110', '5111',
            '5112', '5113', '5114', '5115', '5116', '5117', '5118', '5119', '5120',
            '5122', '5123', '5129', '5151', '5251', '5252', '5253', '5254', '5255',
            '5256', '5257', '5258', '5401', '5402', '5403', '5405', '5551', '5651',
            '5652', '5653', '5654', '5655', '5656', '5667'
        ]);
    }

    /**
     * @param $cfop
     * @return bool
     */
    public static function isCFOPComb($cfop)
    {
        $cfopComb = static::getCFOPGLP();
        $has = false;
        foreach ($cfopComb as $c) {
            if ($c === $cfop) {
                $has = true;
                break;
            }
        }
        return $has;
    }

    /**
     * @param $cfop
     * @param $cProdAnp
     * @return bool
     */
    public static function isOpComb($cfop, $cProdAnp)
    {
        return static::isCFOPComb($cfop) && static::isCodAnpComb($cProdAnp);
    }

    /**
     * @param bool $fromCond
     * @return array
     */
    public static function getTPag($fromCond = true)
    {
        if ($fromCond) {
            $data = [
                ''   => 'Selecione',
            ];
        } else {
            $data = [];
        }
        $data['01'] = '01 - Dinheiro';
        $data['02'] = '02 - Cheque';
        $data['03'] = '03 - Cartão de Crédito';
        $data['04'] = '04 - Cartão de Débito';
        $data['05'] = '05 - Crédito Loja';
        $data['10'] = '10 - Vale Alimentação';
        $data['11'] = '11 - Vale Refeição';
        $data['12'] = '12 - Vale Presente';
        $data['13'] = '13 - Vale Combustível';
        if ($fromCond) {
            $data['14'] = '14 - Duplicata Mercantil';
        }
        $data['15'] = '15 - Boleto Bancário';
        $data['16'] = '16 - Depósito Bancário';
        $data['17'] = '17 - Pagamento Instantâneo (PIX)';
        $data['18'] = '18 - Transferência bancária, Carteira Digital';
        $data['19'] = '19 - Programa de fidelidade, Cashback, Crédito Virtual';
        $data['99'] = '99 - Outros';
        return $data;
    }

    /**
     * @param $message
     * @param int $code
     * @param bool $lancDoc
     */
    public static function log($message, $code = 0, $lancDoc = false)
    {
        $now = CarbonCustom::now();
        $s = DIRECTORY_SEPARATOR;
        if ($lancDoc) {
            $type = "docs";
        } else {
            $type = "nfe";
        }
        $path = "logs" . $s . $type . $s . $now->toDateString() . $s;
        if (Session::has("empresa_padrao")) {
            $path .= str_pad(Session::get("empresa_padrao")->id, 6, "0", STR_PAD_LEFT) . ".log";
        } else {
            $path .= "unset.log";
        }
        $dateTime = $now->toDateTimeString();

        $message = $dateTime . ": error code \"" . $code . "\"; message: \"" . $message . "\"" . PHP_EOL;

        if (\Storage::disk("nfe")->has($path)) {
            \Storage::disk("nfe")->append($path, $message);
        } else {
            \Storage::disk("nfe")->put($path, $message);
        }
    }


    /**
     * @param $nf_id
     * @param $mode
     * @param $instance
     * @return Nfemitidaitem|Nfemitidaitem[]|Nfrecebidaitem|Nfrecebidaitem[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Query\Builder[]|Collection
     */
    public static function getItemsToForm($nf_id, $mode, $instance)
    {
        if ($mode !== "create") {
            if ($instance === "emitida") {
                $items = Nfemitidaitem::from("nfemitidaitems as i")->where("nfemitida_id", $nf_id);
            } else {
                $items = Nfrecebidaitem::from("nfrecebidaitems as i")->where("nfrecebida_id", $nf_id);
            }
            $items = $items->selectRaw("i.*, u.sigla, p.descricao as produto_descricao, s.descricao as setor_descricao, " .
                "o.descricao as nfoperacao_descricao, p.pesoliquido, p.pesobruto, p.ncm, p.ean, p.nfeextipi, " .
                "p.nfcest, p.nfedescricaofiscal, p.nfgrupofiscal_id")
                ->leftJoin("setors as s", 'i.setor_id', "s.id")
                ->leftJoin("produtos as p", 'i.cprod', "p.id")
                ->leftJoin("unidademedidas as u", 'p.unidademedida_id', "u.id")
                ->leftJoin("nfoperacaos as o", 'o.id', "i.nfoperacao_id")
                ->get();
            return $items;
        } else {
            if ($instance === "emitida") {
                return collect([]);
            } else {
                return collect([]);
            }
        }
    }

    /**
     * @param $nf_id
     * @param $mode
     * @param $instance
     * @return Nfemitida|Nfrecebida|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|null
     */
    public static function getNfToForm($nf_id, $mode, $instance)
    {
        if ($mode !== "create") {
            $raw = "nf.*, ccf.descricao as fretecc, pcf.descricao as fretepc, cc.descricao as cc, pc.descricao as pc";
            if ($instance === "emitida") {
                $nf =  Nfemitida::with("financeiro.parcelas", "nfEmitidaCartaCorrecao")->from("nfemitidas as nf");
            } else {
                $nf =  Nfrecebida::with("financeiro.parcelas")->from("nfrecebidas as nf");
            }

            $nf = $nf->leftJoin("centrocustos as ccf", "ccf.id", "fretecentrocusto_id")
                ->leftJoin("planocontas as pcf", "pcf.id", "freteplanoconta_id")
                ->leftJoin("centrocustos as cc", "cc.id", "centrocusto_id")
                ->leftJoin("planocontas as pc", "pc.id", "planoconta_id")
                ->where("nf.id", $nf_id)
                ->selectRaw($raw)->first();

            if (!$nf) {
                Session::flash("message_info", "NF-e/Documento não encontrada no banco de dados");
                \Redirect::intended();
            }
        } else {
            if ($instance === "emitida") {
                $nf = new Nfemitida();
            } else {
                $nf = new Nfrecebida();
            }
            $nf->empresa_id = Session::get("empresa_padrao")->id;
        }
        return $nf;
    }

    public static function getRateioToForm($financeiro_id)
    {
        return Financeirorateio::from("financeirorateios as rat")
            ->selectRaw("rat.*, pc.descricao as planoconta_descricao, pc.codigo as planoconta_codigo, " .
                "cc.descricao as centrocusto_descricao, cc.codigo as centrocusto_codigo")
            ->join("planocontas as pc", "pc.id", "rat.planoconta_id")
            ->join("centrocustos as cc", "cc.id", "rat.centrocusto_id")
            ->where('rat.financeiro_id', $financeiro_id)->get();
    }

    public static function setFieldsFormShowEdit($nf, $items, &$fields, $instance)
    {
        $parcelasfinanceiro = static::getFinanceiroNF($nf->id);
        $freteparcelasfinanceiro = static::getFinanceiroParcela($nf->fretefinanceiro_id);

        $fields['nf' . $instance] = $nf;
        $fields['nf' . $instance . 'items'] = $items;
        $fields['parcelasfinanceiro'] = $parcelasfinanceiro;
        $fields['freteparcelasfinanceiro'] = $freteparcelasfinanceiro;
        $fields['nomecliente'] = $nf->destrazaosocial;
        $fields['rateiocentrocusto_descricao'] = "";
        $fields['rateiocentrocusto_id'] = $nf->rateiocentrocusto_id;
        $fields['rateioplanoconta_descricao'] = "";
        $fields['rateioplanoconta_id'] = $nf->rateioplanoconta_id;
        $fields['rateios'] = static::getRateioToForm($nf->financeiro_id);
    }

    /**
     * Compares and creates rows for revisions of items
     *
     * @param \Illuminate\Database\Eloquent\Model    $model
     * @param array $data
     * @param string $index : index do array data que contem os produtos
     * @param string $key String com a descricao da coluna key
     * @return bool|Revision
     */
    public static function generateRateioLog($model, $data, $index, $key)
    {
        $old = self::getRateiosRevisions($model);
        $new = [];
        if (isset($data[$index]) && $data[$index]) {
            $new = self::mountObject(json_decode($data[$index]));
        } else {
            return false;
        }

        $old_arr = [];
        foreach ($old as $ol) {
            $val = requestNumeroDecimalOracle($ol->valor);
            $old_arr[] = "Planoconta_id: " . $ol->planoconta_id .
                ", Centrocusto_id: " . $ol->centrocusto_id .
                ", Valor: " . $val;
        }

        $new_arr = [];
        foreach ($new as $ne) {
            $new_arr[] = "Planoconta_id: " . $ne->planoconta_id .
                ", Centrocusto_id: " . $ne->centrocusto_id .
                ", Valor: " . $ne->valor;
        }

        $velho = implode(' | ', $old_arr);
        $novo = implode(' | ', $new_arr);
        $isDiff = $velho !== $novo;

        if ($isDiff) {
            return self::createRevisions($velho, $novo, $key, $model);
        }

        return true;
    }

    public static function generateInutilizacaoLog($item, $arrWhatShouldCreate)
    {
        $user_id = \Auth::user()->getAuthIdentifier();
        $revisao = new Revision();
        $revisions = collect([]);
        foreach ($arrWhatShouldCreate as $what) {
            $revision = new Revision();
            $revision->revisionable_type = $item->getMorphClass();
            $revision->revisionable_id = $item->getKey();
            $revision->key = $what;
            $revision->old_value = null;
            $revision->new_value = $item->$what;
            $revision->user_id = $user_id;
            $revision->identity = $item->empresa_id;
            $revision->identityBy = "empresa_id";
            $revision->created_at = Carbon::now();
            $revision->updated_at = Carbon::now();
            $revisions->push($revision);
        }
        $revisao->insert($revisions->toArray());
    }

    /**
     * Method to sort rateios
     * @param array $objects Rateions coming from the view
     * @return object $ratos Sorted rateios by planoconta_id
     */
    private static function mountObject($objects)
    {
        $ratos = collect([]);
        foreach ($objects as $obj) {
            $rato = (object) array();
            $rato->planoconta_id = $obj[3];
            $rato->centrocusto_id = $obj[0];
            $rato->valor = $obj[6];
            $ratos->push($rato);
        }
        $ratos = $ratos->sortBy("planoconta_id");
        return $ratos;
    }

    /**
     *  checa e retorna se o pagamento é a vista ou a prazo
     *
     * @param array $parcelas
     * @param \App\Financeiro $financeiro
     * @param int $countparcelas
     * @return boolean
     */
    public static function isPgtoAvisa($parcelas, $financeiro, $countparcelas)
    {
        if ($countparcelas == 1) {
            $data1 = Carbon::parse($financeiro->dataemissao);
            $data2 = Carbon::parse(insertDataOracle($parcelas[0]["datavenc"]));
            $intervalo = $data1->diff($data2);
            if ($intervalo->d > 5)
                return false;
        } elseif ($countparcelas > 1) {
            return false;
        }
        return true;
    }
    /**
     * Gera o array de FinanceiroParcelas
     *
     * @param int $countparcelas
     * @param array $parcelas
     * @param float $descontoparcelar
     * @param \App\Processors\FinanceiroProcessor $processor
     * @param \Datetime $datahoraemissao
     * @param float $total
     * @return array
     */
    public static function gerarParcelas($countparcelas, $parcelas, $descontoparcelar, $processor, $datahoraemissao, $total, $empresa)
    {
        $parc = collect([]);
        $descRestante = $descontoparcelar * $countparcelas;
        $valorRestante = $total + $descRestante;
        $valorEfeRestante = $total;
        for ($i = 0; $i < $countparcelas; $i++) {
            $desconto = sprintf("%0.2f", $descontoparcelar);
            $valorEfetivado = sprintf("%0.2f", $parcelas[$i]["valor"]);
            $valor = sprintf("%0.2f", $valorEfetivado + $desconto);
            if ($countparcelas - 1 === $i) {
                $desconto = $descRestante;
                $valorEfetivado = $valorEfeRestante;
                $valor = $valorRestante;
            }
            $financeiroparcela = new Financeiroparcela();
            $financeiroparcela["datavencimento"] = insertDataOracle($parcelas[$i]["datavenc"]);
            $financeiroparcela["valor"] = $valor;
            $financeiroparcela["empresa_id"] = $empresa->id;
            $financeiroparcela["grupo_id"] = $empresa->grupo_id;
            $financeiroparcela["financeiro_id"] = $processor->getFinanceiro()->id;
            $financeiroparcela["multa"] = 0;
            $financeiroparcela["juros"] = 0;
            $financeiroparcela["desconto"] = $desconto;
            $financeiroparcela["valorefetivado"] = $valorEfetivado;
            $financeiroparcela["numero"] = $i + 1;
            $financeiroparcela["baixado"] = false;
            $financeiroparcela["datacompetencia"] = $datahoraemissao;
            $financeiroparcela["pagarreceber"] = $processor->getFinanceiro()->pagarreceber;
            $parc->push($financeiroparcela);
            $descRestante -= $desconto;
            $valorRestante -= $valor;
            $valorEfeRestante -= $valorEfetivado;
        }

        return $parc;
    }

    public static function isMono($cst) {
       return $cst == "61";
    }

    private static function checkChaveExistance($chave)
    {
        $nfRef = Nfemitida::where("chaveacesso", $chave)->get()->first();

        if (is_null($nfRef)) {
            throw new Exception("Chave de Acesso referenciada não foi encontrada na base de dados.");
        }

        if ($nfRef->nfmodelo != "65") {
            throw new Exception("CFOP 5929 deve referenciar uma NFC-e modelo (65)!");
        }
    }

    public static function isMonoIBSCBS($cst) {
       return $cst == "620";
    }

    public static function useREDBCIBS($cst = null, $getArray = false)
    {
        $aValidate = [''];
        if ($getArray)
            return $aValidate;
        else
            return in_array($cst, $aValidate);
    }

}
