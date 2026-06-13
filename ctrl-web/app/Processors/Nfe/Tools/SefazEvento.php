<?php

namespace App\Processors\Nfe\Tools;

use App\Helpers\Utils\NfUtil as Util;
use Illuminate\Support\Collection;
use Session;
use DB;
use App\Empresa;
use NFePHP\{
    Common\Exception\RuntimeException,
    DA\NFe\Danfe,
    DA\NFe\Danfce,
    DA\NFe\Dacce,
    DA\Legacy\Pdf,
    NFe\Complements,
    NFe\Common\Standardize,
    Common\DOMImproved as Dom
};
use App\Http\Controllers\NfemitidaController;
use App\Services\CarbonCustom as Carbon;
use App\Nfemitida;
use \Exception;
use App\Nfemitidacartacorrecao;
use App\Empresainutilizacao;

/**
 * Classe com as funções de eventos da SEFAZ
 *
 * @author jeferson
 */
class SefazEvento
{

    /**
     * @var mixed
     */
    private $nf;

    /**
     * @var mixed
     */
    private $processamento = [103, 105];

    /**
     * @var \NFePHP\NFe\Tools
     */
    private $tools;

    /**
     * @var mixed
     */
    private $ambiente;

    /**
     * SefazEvento constructor.
     * @param $id
     * @throws Exception
     */
    public function __construct($id)
    {
        if ($id instanceof Nfemitida) {
            $this->nf = $id;
        } elseif (is_integer($id)) {
            $this->nf = Nfemitida::find($id);
        } else {
            return;
        }

        if (is_null($this->nf)) {
            throw new Exception("Ocorreu um erro ao buscar a NF-e no banco de dados");
        }

        $this->setAmbiente($this->nf->nftipoambiente);
        $this->initTools($this->nf);
        $this->setModelo($this->nf->nfmodelo);
    }

    /**
     * @param null $nf
     * @param null $cnpj
     * @param null $nfmodelo
     * @param null $empresa
     * @throws Exception
     */
    public function initTools($nf = null, $cnpj = null, $nfmodelo = null, $empresa = null)
    {
        $tools = new Tools();
        $this->tools = $tools->mount($nf, Util::getConfigTools($nf, $cnpj, $nfmodelo, $empresa), $cnpj, $empresa);
    }

    /**
     * @param $tpAmb
     */
    function setAmbiente($tpAmb)
    {
        $this->ambiente = Util::getAmbiente($tpAmb);
    }

    /**
     * @param $model
     */
    public function setModelo($model)
    {
        $this->tools->model($model);
    }

    /**
     * @return Nfemitida|Nfemitida[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model|mixed|null
     */
    public function getNf()
    {
        return $this->nf;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function enviaLote()
    {
        DB::beginTransaction();
        $msg = "[ID: {$this->nf->id}, Número: {$this->nf->nfnumero}]";
        $indSinc = 1;
        try {

            $isEPEC = Util::getTypeContingency($this->nf, true) === "EPEC";
            $xml = Util::getXmlFile($this->nf->emitcnpj, $this->ambiente, $this->nf->nfmodelo, $this->nf->chaveacesso, "assinadas");

            if ($isEPEC && $this->nf->empresa->contingenciaemissao) {
                $this->tools->setVerAplic('1.0.1');
                $retorno = $this->tools->sefazEPEC($xml);
                $path = 'epec';
                $msg = "em Contingência EPEC: " . $msg;
            } else {
                $isEPEC = false;
                $retorno = $this->tools->sefazEnviaLote([$xml], $this->nf->nfnumero, $indSinc);
                $path = 'transmitidas';
                $msg = ":" . $msg;
            }

            Util::saveXML($this->nf->emitcnpj, $retorno, $this->nf->nfmodelo, $this->ambiente, $this->nf->chaveacesso, $path);

            //transforma o xml de retorno em um stdClass
            $standardize = new Standardize();
            $stdXml = $standardize->toStd($retorno);

            if ($stdXml->cStat == 128 && $isEPEC && $this->nf->empresa->contingenciaemissao) {
                $infEv = $stdXml->retEvento->infEvento;
                $cStat = $infEv->cStat;
                $xMotivo = $infEv->xMotivo;
                $nProt = isset($infEv->nProt) ? $infEv->nProt : $this->nf->epecprotocolo;
                if ($cStat == 136) {
                    $msg = "Sucesso: Nota Fiscal Transmitida $msg";
                    $this->nf->update([
                        'epecprotocolo'    => $nProt,
                        'nfsituacao_id'    => $cStat,
                        'epecxml'          => $retorno,
                        'epecstatusevento' => $cStat
                    ]);
                } else {
                    $msg = "Erro: $cStat - $xMotivo";
                }
                DB::commit();
                return $msg;
            }

            $dataError = ["nfsituacao_id" => $stdXml->cStat, "xmlretorno" => $retorno];

            if (in_array($stdXml->cStat, $this->processamento)) {
                $this->nf->update([
                    "numeroreciboenvio" => $stdXml->infRec->nRec,
                    "nfsituacao_id"     => 3,
                    "xmlretorno"        => $retorno
                ]);
                DB::commit();
                return "Sucesso: Nota Fiscal Transmitida $msg";
            } else if ($stdXml->cStat == 104) {
                $infProt = $stdXml->protNFe->infProt;

                $cStat = $infProt->cStat;
                $nProt = isset($infProt->nProt) ? $infProt->nProt : '';
                $xMotivo = $infProt->xMotivo;
                $data = [
                    "xmlretorno"    => $retorno,
                    "nfsituacao_id" => $cStat,
                    "protocolo"     => $nProt
                ];
                $returnMsg = "Sucesso: Nota Fiscal Transmitida $msg";

                if ($cStat != 100) {
                    $data = $dataError;
                    $returnMsg = "Erro: $cStat - $xMotivo";
                }

                $this->nf->update($data);
                DB::commit();

                return $returnMsg;
            } else {
                $this->nf->update($dataError);
                $cStat = $stdXml->cStat;
                $xMotivo = $stdXml->xMotivo;
                DB::commit();
                return "Erro: $cStat - $xMotivo";
            }
        } catch (Exception $e) {
            DB::rollback();
            return "Erro: " . $e->getMessage() . " $msg";
        }
    }

    /**
     * @return object|\stdClass
     * @throws Exception
     */
    public function consultaRecibo()
    {
        $tpAmb = $this->nf->nftipoambiente;
        $recibo = $this->nf->numeroreciboenvio;
        if ($this->nf->empresa->contingenciaemissao) {
            return (object) ['cStat' => 0, 'xMotivo' => "Desative o modo de contingência na empresa"];
        }

        $xmlRetorno = $this->tools->sefazConsultaRecibo($recibo, $tpAmb);

        $standardize = new Standardize();
        $stdXml = $standardize->toStd($xmlRetorno);

        if (isset($stdXml->cStat) && in_array((int) $stdXml->cStat, [105, 656, 106])) {
            return $stdXml;
        }

        $cStat = $stdXml->protNFe->infProt->cStat;
        $nProt = isset($stdXml->protNFe->infProt->nProt) ? $stdXml->protNFe->infProt->nProt : '';
        $xMotivo = $stdXml->protNFe->infProt->xMotivo;

        $data = [
            "xmlretorno" => $xmlRetorno
        ];
        if (strlen($cStat) > 0)
            $data["nfsituacao_id"] = $cStat;

        if (strlen($nProt) > 0)
            $data["protocolo"] = $nProt;

        $this->nf->update($data);

        return (object) [
            'cStat'   => $cStat,
            'nProt'   => $nProt,
            'xMotivo' => $xMotivo,
        ];
    }

    /**
     * @return $this
     * @throws Exception
     */
    public function addAuthorized()
    {
        $pathNFefile = $this->nf->xmlassinado;

        $pathProtfile = $this->nf->xmlretorno;

        $retorno = Complements::toAuthorize($pathNFefile, $pathProtfile);

        $this->nf->update(["xmlretornocompleto" => $retorno]);
        $this->nf->xmlretornocompleto = $retorno;

        Util::saveXML($this->nf->emitcnpj, $retorno, $this->nf->nfmodelo, $this->ambiente, $this->nf->chaveacesso, "protocoloadas");
        return $this;
    }

    /**
     * @param $fromPedidos
     * @param $cStat
     * @return mixed
     * @throws Exception
     */
    public function emiteDanfe($fromPedidos, $cStat, $fromAppNF = false)
    {
        if ($cStat == 135 && $this->nf->xmlretornocancelamento) {
            $docXml = $this->nf->xmlretornocancelamento;
        } elseif ($cStat == 136) {
            $docXml = $this->nf->xml;
        } else {
            $docXml = $this->nf->xmlretornocompleto;
        }

        if (is_null($docXml)) {
            throw new Exception("Xml da NF-e não encontrado");
        }

        $directoryPdf = Util::getPDFPath($this->nf->chaveacesso, $this->nf->emitcnpj, $this->nf->nfmodelo == 55 ? 'danfe' : 'danfce');

        $pdf = $this->mountPdf($this->getDanfeClass($docXml));

        if ($this->nf->nfmodelo == "55") {
            $mod = "NF-e ";
        } else {
            $mod = "NFC-e ";
        }

        $pdf->SetTitle($mod . $this->nf->nfnumero);

        $pdf->Output($directoryPdf, 'f'); //save
        //$fromPedidos ? "D" : "I";
        return $pdf->Output($directoryPdf, $fromPedidos ? "I" : ($fromAppNF ? "S" : "I")); //print
    }

    /**
     * @param $docXml
     * @return Danfce|Danfe
     * @throws Exception
     */
    private function getDanfeClass($docXml)
    {
        $file = Util::getLogoPath($this->nf->emitcnpj);

        $inputLogo = $file . ".png";
        $outputLogo = $file . ".jpg";

        if (removeTransparencyImg($inputLogo, $outputLogo)) {
            $debugMode = 1;
            $dir = Util::getPDFPath($this->nf->chaveacesso, $this->nf->emitcnpj, '', false);
            if (!is_dir($dir)) {
                mkdir($dir);
            }
            if ($this->nf->nfmodelo == 55) {
                $danfe = new Danfe($docXml, 'P', 'A4', $outputLogo, 'I', '', '', $debugMode);
                $danfe->exibirTextoFatura = true;
                return $danfe;
            } else if ($this->nf->nfmodelo == 65) {
                return new Danfce($docXml, $outputLogo, $debugMode);
            } else {
                throw new Exception("Modelo de NF inválido");
            }
        } else {
            throw new Exception("Não foi possível carregar a logo da empresa");
        }
    }

    /**
     * @param Danfe|Danfce $danfe
     * @return Pdf
     */
    private function mountPdf($danfe)
    {
        $pdf = new Pdf();
        $sitExt = $this->nf->empresa->contingenciaemissao ? 3 : 0;
        $cDanfe = $danfe->montaDANFE('', 'A4', 'C', $sitExt, $pdf, strval($this->nf->epecprotocolo));
        return $cDanfe['classe_PDF'];
    }

    /**
     * @param $data
     * @return string
     * @throws Exception
     */
    public function inutilizarFaixaNFs($data)
    {
        $this->treatNumNfInutilizacao($data);

        $controller = new NfemitidaController();
        $nfemitidas = Nfemitida::with(["nfEmitidaItem", "nfEmitidaVolume"])->where('nfmodelo', $data["nfmodelo"])
            ->where('nfserie', $data['nfeserie'])
            ->whereBetween('nfnumero', [$data['nini'], $data['nfin']])
            ->where('nftipoambiente', $data['nftipoambiente'])
            ->where('empresa_id', $data["empresa_id"])
            ->get();

        foreach ($nfemitidas as $nf) {
            if (Util::isAuthorized($nf->nfsituacao_id)) {
                $msg = "Nota fiscal número [" . $nf->nfnumero .
                    "] e série [" . $nf->nfserie . "] já foi autorizada pela receita "
                    . "e não pode ser inutilizada";
                throw new \Exception($msg);
            }
            $controller->estornoFinanceiroEstoque($nf, "Inutilização de números, " . $data['xjust'], false);
            $nf->update(['inutilizarcancelar' => 1]);
        }

        $aRetorno = $this->tools->sefazInutiliza($data['nfeserie'], $data['nini'], $data['nfin'], $data['xjust'], $data['nftipoambiente']);

        $retorno = $this->finalizaInutilizacaoNf($aRetorno, $data, $nfemitidas);

        return $retorno;
    }

    /**
     * @param $aRetorno
     * @param $data
     * @param Nfemitida|Collection $nfemitidas
     * @return string
     * @throws Exception
     */
    private function finalizaInutilizacaoNf($aRetorno, $data, $nfemitidas)
    {
        $inutilizacaoBase = $this->newInstanceEmpresaInutilizacao($data, $aRetorno);

        $stdCl = new Standardize($aRetorno);
        $std = $stdCl->toStd();
        $infInut = $std->infInut;
        $cStat = (int) $infInut->cStat;
        $xMotivo = $infInut->xMotivo;

        $inutilizacaos = Empresainutilizacao::where('empresa_id', $inutilizacaoBase->empresa_id)
            ->where('nfmodelo', $inutilizacaoBase->nfmodelo)
            ->where('nfeserie', $inutilizacaoBase->nfeserie)
            ->where('nfetipoambiente', $inutilizacaoBase->nfetipoambiente)
            ->where('nini', '>=', $data['nini'])
            ->where('nfin', '<=', $data['nfin'])
            ->get();
        //Ja existe pedido de Inutilizacao com a mesma faixa de inutilizacao.
        if ($cStat === 563) {
            $inutilizacaoBase->protocolo = $this->extractProtInutilizacaoErro($xMotivo);

            $this->saveManyInutilizacao($inutilizacaoBase, $data, $inutilizacaos);
            return "Inutilização não realizada mas armazenada no banco de "
                . "dados, cód: " . $cStat . " , msg: " . $xMotivo;
        } elseif ($cStat === 102) {
            $nProt = $infInut->nProt;
            $inutilizacaoBase->protocolo = $nProt;
            foreach ($nfemitidas as $nf) {
                $nf->update([
                    "nfsituacao_id" => $cStat,
                    "protocolo"     => $nProt
                ]);
            }

            $this->saveManyInutilizacao($inutilizacaoBase, $data, $inutilizacaos);

            return "Inutilização realizada, protocolo: " . $nProt . ", cód: "
                . $cStat . ", msg: " . $infInut->xMotivo;
        }

        return "Inutilização não realizada, cód: " . $cStat . ", msg: " . $xMotivo;
    }

    /**
     * @param $xMotivo
     * @return null|string|string[]
     * @throws Exception
     */
    private function extractProtInutilizacaoErro($xMotivo)
    {
        $arr = explode('nProt:', $xMotivo);

        if (!array_key_exists(1, $arr)) {
            throw new \Exception('Impossível extrair o protocolo da inutilização');
        }
        return preg_replace('/[^0-9]/', '', $arr[1]);
    }

    /**
     * @param Collection|\Eloquent $inutilizacaoBase
     * @param $data
     * @param Collection $inutilizacaos
     * @return bool
     */
    private function saveManyInutilizacao($inutilizacaoBase, $data, $inutilizacaos)
    {
        $whatShouldCreateRevision = ["created_at", "nfmodelo", "protocolo", "nini", "nfin"];
        $inutil = null;
        for ($i = $data['nini']; $i <= $data['nfin']; $i++) {
            $inu = clone $inutilizacaoBase;
            $filter = function ($el) use ($i) {
                return $el->nini == $i && $el->nfin == $i;
            };
            if ($inutilizacaos->filter($filter)->count()) {
                continue;
            } else {
                $inu->nini = $i;
                $inu->nfin = $i;
                $inu->save();
                $inutil = $inu;
            }
        }
        $inutil->nini = $data['nini'];
        Util::generateInutilizacaoLog($inutil, $whatShouldCreateRevision);
        return true;
    }

    /**
     * @param $data
     * @param $aRetorno
     * @return Empresainutilizacao
     */
    private function newInstanceEmpresaInutilizacao($data, $aRetorno)
    {

        $dom = new Dom();
        $dom->loadXMLString($aRetorno);

        $model = new Empresainutilizacao();
        $model->xmlenvio = $dom->saveHTML($dom->getNode('infInut'));
        $model->xmlretorno = $dom->saveHTML($dom->getNode('retInutNFe'));
        $model->empresa_id = $data['empresa_id'];
        $model->grupo_id = Session::get('empresa_padrao')->grupo_id;
        $model->user_id = \Auth::user()->id;
        $model->datahora = Carbon::now();
        $model->xjust = $data['xjust'];
        $model->nfmodelo = $data['nfmodelo'];
        $model->nfeserie = $data['nfeserie'];
        $model->nfetipoambiente = $data['nfetipoambiente'];
        $model->nfsituacao_id = 102;

        return $model;
    }

    /**
     * @param $data
     * @return bool
     * @throws Exception
     */
    private function treatNumNfInutilizacao($data)
    {

        $empresa = Empresa::find($data['empresa_id']);

        if (is_null($empresa)) {
            throw new Exception("Empresa não encontrada no banco de dados");
        }

        $nfmodelo = (int) $data['nfmodelo'];
        $nftipoambiente = (int) $data['nftipoambiente'];
        $numFin = (int) $data['nfin'];
        // Produção
        if ($nftipoambiente === 1) {
            if ($nfmodelo === 55 && $empresa->nfenumero < $numFin) {
                $dataUp = ['nfenumero' => $numFin];
            } elseif ($nfmodelo === 65 && $empresa->nfnumero < $numFin) {
                $dataUp = ['nfcenumero' => $numFin];
            }
            // Homologação
        } elseif ($nftipoambiente === 2) {
            if ($nfmodelo === 55 && $empresa->nfenumerohomologacao < $numFin) {
                $dataUp = ['nfenumerohomologacao' => $numFin];
            } elseif ($nfmodelo === 65 && $empresa->nfcenumerohomologacao < $numFin) {
                $dataUp = ['nfcenumerohomologacao' => $numFin];
            }
        }
        return isset($dataUp) ? $empresa->update($dataUp) : true;
    }

    /**
     * @param $xJust
     * @param $callbackSuccess
     * @return string
     * @throws Exception
     */
    public function cancelarNF($xJust, $callbackSuccess)
    {
        DB::beginTransaction();
        try {
            $sitPrev = (int) $this->nf->nfsituacao_id;
            $cStatConsulta = $this->consultaChave()->cStat;
            if (!Util::isAuthorized($sitPrev)) {
                throw new Exception("Somente notas autorizadas podem ser canceladas.");
            }
            $response = $this->tools->sefazCancela($this->nf->chaveacesso, $xJust, $this->nf->protocolo);

            $stdCl = new Standardize($response);
            $std = $stdCl->toStd();
            $cStat = $std->cStat;

            if ($std->cStat != 128) {
                $xMotivo = $std->xMotivo;
            } else {
                $cStat = $std->retEvento->infEvento->cStat;
                $xMotivo = $std->retEvento->infEvento->xMotivo;
                if ($cStat == '101' || $cStat == '155' || $cStat == '135') {
                    $nProt = $std->retEvento->infEvento->nProt;
                    //SUCESSO PROTOCOLAR A SOLICITAÇÂO ANTES DE GUARDAR
                    $xml = Complements::toAuthorize($this->tools->lastRequest, $response);
                    //grave o XML protocolado
                    Util::saveXML($this->nf->emitcnpj, $xml, $this->nf->nfmodelo, $this->ambiente, $this->nf->chaveacesso, 'canceladas');

                    $xmlcompletocancelado = Complements::cancelRegister($this->nf->xmlretornocompleto, $response);
                    Util::saveXML($this->nf->emitcnpj, $xmlcompletocancelado, $this->nf->nfmodelo, $this->ambiente, $this->nf->chaveacesso, 'retEnvEvento');
                    $this->nf->update([
                        "nfsituacao_id"                => $cStat,
                        "cancelamentoevexmlretorno"    => $xml,
                        "cancelamentoeveprotocoloret"  => $nProt,
                        "protocoloretornocancelamento" => $nProt,
                        "cancelamentomotivo"           => $xJust,
                        "xmlretornocancelamento"       => $xmlcompletocancelado
                    ]);
                }
            }

            if ($cStat == 135) {
                call_user_func($callbackSuccess);
                DB::commit();
                return "NF cancelada com sucesso! $xMotivo protocolo: {$this->nf->cancelamentoeveprotocoloret}";
            } else if ($cStat == 573) {
                if ($cStatConsulta == 101 && $sitPrev !== 135 && $sitPrev !== 101) {
                    call_user_func($callbackSuccess);
                    DB::commit();
                } else {
                    DB::rollback();
                }
                return "Problemas ao cancelar NF! Cód status $cStat Motivo: $xMotivo";
            } else {
                if ($cStatConsulta == 101 && $sitPrev !== 135 && $sitPrev !== 101) {
                    call_user_func($callbackSuccess);
                    DB::commit();
                } else {
                    DB::rollback();
                }
                return "Erro no evento de cancelamento! Cód status $cStat Motivo: $xMotivo";
            }
        } catch (Exception $e) {
            DB::rollback();
            return $e->getMessage();
        }
    }

    /**
     * @param $xCorrecao
     * @param $create
     * @param $hash
     * @return string
     * @throws Exception
     */
    public function cartaCorrecaoNF($xCorrecao, $create, $hash)
    {
        DB::beginTransaction();
        try {
            $nfemitida = $this->nf;
            $cce = Nfemitidacartacorrecao::where('nfemitida_id', $nfemitida->id)
                ->where('nfnumero', $nfemitida->nfnumero)
                ->where('tpamb', $nfemitida->nftipoambiente)->get();
            $cceHash = $cce->where('hash', $hash)->first();
            if (!is_null($cceHash) && $create) {
                $create = false;
                $nSeqEvento = $cceHash->nseqevento;
            } else {
                $nSeqEvento = count($cce) > 0 ? (int) $cce->max('nseqevento') + 1 : 1;
            }

            if (!$create) {
                $filename = $nfemitida->chaveacesso . '-' . ($nSeqEvento - 1);
                $xml = $this->getXmlCCE($cceHash, $filename);
                $this->printCCE($xml, $nfemitida);

                return "Carta de correção consultada com sucesso!";
            } else {
                $filename = $nfemitida->chaveacesso . '-' . $nSeqEvento;
                $cce = $this->createCCE($hash, $nSeqEvento, $xCorrecao);

                $std = $this->sendCCE($xCorrecao, $nSeqEvento);

                $cStat = $std->cStat;

                if ($cStat != 128) {
                    $xMotivo = $std->xMotivo;
                    $nProt = $std->nProt;
                } else {
                    $cStat = $std->retEvento->infEvento->cStat;
                    $xMotivo = $std->retEvento->infEvento->xMotivo;
                    if ($cStat == '135' || $cStat == '136') {

                        $nProt = $std->retEvento->infEvento->nProt;
                        //SUCESSO PROTOCOLAR A SOLICITAÇÂO ANTES DE GUARDAR
                        $xml = Complements::toAuthorize($this->tools->lastRequest, $std->response);
                        //grave o XML protocolado
                        Util::saveXML($this->nf->emitcnpj, $xml, $this->nf->nfmodelo, $this->ambiente, $filename, "CCE");
                        $nfemitida->update([
                            'nfsituacao_id'                 => $cStat,
                            'xmlretornoeventocartacorrecao' => $xml,
                            'protocoloretevecartacorrecao'  => $nProt
                        ]);
                        $cce->update(['xmlretornoevento' => $xml, 'protocoloretornoevento' => $nProt]);

                        $this->printCCE($xml, $nfemitida);
                    } else {
                        $nProt = '';
                    }
                }

                if ($cStat == 135 || $cStat == 136) {
                    DB::commit();
                    return "Carta de correção enviada com sucesso! $xMotivo protocolo: $nProt";
                } else {
                    DB::commit();
                    return "Erro no evento de carta de correção! Cód status $cStat Motivo: $xMotivo";
                }
            }
        } catch (Exception $e) {
            DB::commit();
            return $e->getMessage();
        }
    }

    /**
     * @param $cceHash
     * @param $filename
     * @return bool|string
     * @throws Exception
     */
    private function getXmlCCE($cceHash, $filename)
    {
        try {
            $xml = Util::getXmlFile($this->nf->emitcnpj, $this->ambiente, $this->nf->nfmodelo, $filename, 'CCE');
        } catch (Exception $ex) {
            if ($ex->getCode() === 404 && isset($cceHash->xmlretornoevento)) {
                $xml = $cceHash->xmlretornoevento;
                Util::saveXML($this->nf->emitcnpj, $cceHash->xmlretornoevento, $this->nf->nfmodelo, $this->ambiente, $filename, "CCE");
            } else {
                throw $ex;
            }
        }
        return $xml;
    }

    /**
     * @param $xCorrecao
     * @param $nSeqEvento
     * @return \stdClass
     */
    private function sendCCE($xCorrecao, $nSeqEvento)
    {
        $response = $this->tools->sefazCCe($this->nf->chaveacesso, $xCorrecao, $nSeqEvento);

        $stdCl = new Standardize($response);
        $std = $stdCl->toStd();
        $std->response = $response;
        return $std;
    }

    /**
     * @param $hash
     * @param $nSeqEvento
     * @param $xCorrecao
     * @return Nfemitidacartacorrecao|\Illuminate\Database\Eloquent\Model
     */
    private function createCCE($hash, $nSeqEvento, $xCorrecao)
    {
        $data = [];
        $data["grupo_id"] = $this->nf->empresa->grupo_id;
        $data["empresa_id"] = $this->nf->empresa->id;
        $data["nfemitida_id"] = $this->nf->id;
        $data["nfnumero"] = $this->nf->nfnumero;
        $data["idlote"] = $this->nf->nfnumero;
        $data["tpamb"] = $this->nf->nftipoambiente;
        $data["cnpj"] = $this->nf->emitcnpj;
        $data["chnfe"] = $this->nf->chaveacesso;
        $data["hash"] = $hash;
        $data["nseqevento"] = $nSeqEvento;
        $data["verevento"] = '2.00';
        $data["descevento"] = 'Carta de Correção';
        $data["xcorrecao"] = $xCorrecao;
        $data["xconduso"] = 'A Carta de Correcao e disciplinada pelo paragrafo 1o-A do art. 7o do Convenio S/N, de 15 de dezembro de 1970 e pode ser utilizada para regularizacao de erro ocorrido na emissao de documento fiscal, desde que o erro nao esteja relacionado com: I - as variaveis que determinam o valor do imposto tais como: base de calculo, aliquota, diferenca de preco, quantidade, valor da operacao ou da prestacao; II - a correcao de dados cadastrais que implique mudanca do remetente ou do destinatario; III - a data de emissao ou de saida.';
        $data["datahoraevento"] = Carbon::now();

        return Nfemitidacartacorrecao::create($data);
    }

    /**
     * @param $xml
     * @param $nfemitida
     * @throws Exception
     */
    private function printCCE($xml, $nfemitida)
    {
        $file = Util::getLogoPath($nfemitida->emitcnpj);

        $aEnd = array(
            'razao'       => $nfemitida->emitrazaosocial,
            'logradouro'  => $nfemitida->emitendereco,
            'numero'      => $nfemitida->emitnumero,
            'complemento' => $nfemitida->emitcomplemento,
            'bairro'      => $nfemitida->emitbairro,
            'CEP'         => $nfemitida->emitcep,
            'municipio'   => $nfemitida->emitcidadenome,
            'UF'          => $nfemitida->emituf,
            'telefone'    => $nfemitida->emittelefone,
            'email'       => $nfemitida->empresa->email
        );

        $inputLogo = $file . ".png";
        $outputLogo = $file . ".jpg";

        if (removeTransparencyImg($inputLogo, $outputLogo)) {
            $dir = Util::getPDFPath($nfemitida->chaveacesso, $nfemitida->emitcnpj, '', false);
            if (!is_dir($dir))
                mkdir($dir);
            $directoryPdf = Util::getPDFPath($nfemitida->chaveacesso, $nfemitida->emitcnpj, 'CCE');
            $dacce = new Dacce($xml, 'P', 'A4', $outputLogo, 'I', $aEnd, $directoryPdf);
            $dacce->printDACCE($directoryPdf, 'I');
        } else {
            throw new Exception("Não foi possível localizar a logo da empresa");
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    public function enviarMailNF()
    {
        $nfemitida = $this->nf;
        if (!$nfemitida->destemail) {
            throw new Exception("Email do destinatário não encontrado");
        }

        $config = DB::table('empresaconfigs c')
            ->where('c.empresa_id', $nfemitida->empresa_id)
            ->get()->first();
        if (is_null($config)) {
            throw new Exception("Não foi possível localizar as configurações da empresa");
        }
        try {
            // $files = $this->toZipFilesMail();
            $type = $this->nf->nfmodelo == 55 ? 'danfe' : 'danfce';
            sendMail([
                'content' => $config->emailcorpo,
                'subject' => $config->emailassunto,
                'to'      => [
                    $nfemitida->destemail
                ],
                'files'   => [
                    Util::getXmlFile($this->nf->emitcnpj, $this->ambiente, $this->nf->nfmodelo, $this->nf->chaveacesso, 'protocoloadas', false),
                    Util::getPDFPath($this->nf->chaveacesso, $this->nf->emitcnpj, $type)
                ]
            ]);
            // unlink($files->namezip);
        } catch (RuntimeException $e) {
            return [
                'cStat'   => 0,
                'xMotivo' => $e->getMessage(),
                'nProt'   => 0
            ];
        } catch (Exception $e) {
            return [
                'cStat'   => 0,
                'xMotivo' => $e->getMessage(),
                'nProt'   => 0
            ];
        }

        return [
            'cStat'   => 999999,
            'xMotivo' => "Email enviado",
            'nProt'   => 1234567890
        ];
    }

    /**
     * @return object
     * @throws Exception
     */
    // private function toZipFilesMail()
    // {
    //     $type = $this->nf->nfmodelo == 55 ? 'danfe' : 'danfce';
    //     $aArchive = [
    //         'xml' => [
    //             'file' => Util::getXmlFile($this->nf->emitcnpj, $this->ambiente, $this->nf->nfmodelo, $this->nf->chaveacesso, 'protocoloadas', false),
    //             'name' => $this->nf->chaveacesso . '.xml'
    //         ],
    //         'pdf' => [
    //             'file' => file_get_contents(Util::getPDFPath($this->nf->chaveacesso, $this->nf->emitcnpj, $type)),
    //             'name' => $this->nf->chaveacesso . '.pdf'
    //         ]
    //     ];
    //     $ex = 'Problemas ao compactar os arquivos para enviar email';
    //     return zipFiles($aArchive, 'arquivos_nfe.zip', $ex);
    // }

    /**
     * @return \stdClass
     */
    public function consultaChave()
    {
        $xmlRetorno = $this->tools->sefazConsultaChave($this->nf->chaveacesso, $this->nf->nftipoambiente);

        $standardize = new Standardize();
        $stdXml = $standardize->toStd($xmlRetorno);
        if (isset($stdXml->cStat) && (int) $stdXml->cStat === 656)
            return $stdXml;

        return $stdXml;
    }

    /**
     * @param $type
     * @param mixed ...$args
     * @return object|\stdClass|string
     * @throws Exception
     */
    public function evento($type, ...$args)
    {
        $type = strtoupper($type);

        switch ($type) {
            case "CCE":
                $xCorrecao = $args[0];
                $create = $args[1];
                $hash = $args[2];
                return $this->cartaCorrecaoNF($xCorrecao, $create, $hash);

            case "CANCELAR":
                return $this->cancelarNF($args[0], $args[1]);

            case "TRANSMITIR":
                return $this->enviaLote();

            case "CONSULTAR":
                return $this->consultaRecibo();

            default:
                throw new Exception("Evento informado é inválido");
        }
    }
}
