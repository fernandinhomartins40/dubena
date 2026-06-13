<?php
namespace App\Processors\Sped\Contribuicao\BlocoC;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;

use App\Processors\Sped\Util;
use App\Empresa;
use Session;
// REGISTRO C500 NOTA FISCAL/CONTA DE ENERGIA ELÉTRICA (CÓDIGO 06),
// NOTA FISCAL/CONTA DE FORNECIMENTO D'ÁGUA CANALIZADA (CÓDIGO 29)
// E NOTA FISCAL CONSUMO FORNECIMENTO DE GÁS (CÓDIGO 28)
class RegC500 extends AbstractReg
{
    // Código do participante (campo 02 do Registro 0150):
    //     - do emitente do documento, no caso de emissão de terceiros;
    //     - do adquirente, no caso de serviços prestados.
    protected $COD_PART;
    // Código do modelo do documento fiscal, conforme a Tabela 4.1.1
    protected $COD_MOD;
    // Código da situação do documento fiscal:
    //     00 – Documento regular
    //     02 – Documento cancelado
    protected $COD_SIT;
    // Série do documento fiscal
    protected $SER;
    // Sub Série do documento fiscal
    protected $SUB;
    // Número do documento fiscal ou documento internacional equivalente
    protected $NUM_DOC;
    // Data da emissão do documento fiscal
    protected $DT_DOC;
    // Data da entrada ou da saída
    protected $DT_ENT;
    // Valor total do documento
    protected $VL_DOC;
    // Valor do ICMS
    protected $VL_ICMS;
    // Código da informação complementar do documento fiscal (campo 02 do Registro 0450)
    protected $COD_INF;
    // Valor total do PIS
    protected $VL_PIS;
    // Valor total da COFINS
    protected $VL_COFINS;

    protected function setAttributes($data = [])
    {
        $notas = $data['nf']->filter(function ($nf) {
            return Util::hasIn($nf->nfmodelo, ['06','28','29']) && $nf->tiponf == 'recebida'; 
        })->unique('nf_id');

        if($notas->count() === 0)
            $this->none = true;
        
        foreach ($notas as $nf) {
            $this->COD_PART = $nf->cliente_id;
            $this->COD_MOD = $nf->nfmodelo;

            if ($nf->nfsituacao_id == "100")
                $this->COD_SIT = "00";
            else if ($nf->nfsituacao_id == "101")
                $this->COD_SIT = "02";
            else
                $this->COD_SIT = "99";

            $this->SER = $nf->nfserie;
            $this->SUB = $nf->nfsubserie;
            $this->NUM_DOC = $nf->nfnumero;
            $this->DT_DOC = Util::dateFormat($nf->datahoraemissao);
            $this->DT_ENT = Util::dateFormat($nf->datahoraentradasaida);
            $this->VL_DOC = Util::numberFormat($nf->vnf);
            $this->VL_ICMS = Util::numberFormat($nf->vicmsnf);
            $this->COD_INF = "";
            $this->VL_PIS = Util::numberFormat($nf->vpisnf);
            $this->VL_COFINS = Util::numberFormat($nf->vcofinsnf);

            $items = $notas->filter(function ($i) use ($nf) {
                return Util::hasIn($i->nfmodelo, ['06','28','29']) && $i->tiponf == 'recebida' && $i->nf_id == $nf->nf_id; 
            });

            $uniqueItems = $items->unique(function ($i) {
                return $i->cstpis.$i->cstcofins.$i->piscofinstipobccredito.$i->ppis.$i->pcofins;
            })->sortBy('item_id');
            
            foreach ($uniqueItems as $uItem) {

                //filtrando todos os itens que possuem esses campos 
                //e somando para atribuir os campos dos registros
                $itemFilter = $items->filter(function ($i) use($uItem)
                {
                    return $i->cstpis === $uItem->cstpis    
                        && $i->cstcofins === $uItem->cstcofins 
                        && $i->piscofinstipobccredito === $uItem->piscofinstipobccredito 
                        && $i->ppis === $uItem->ppis
                        && $i->pcofins === $uItem->pcofins;
                });

                $uItem->vprod = $itemFilter->sum('vprod');
                $uItem->vpis = $itemFilter->sum('vpis');
                $uItem->vbcpis = $itemFilter->sum('vbcpis');
                $uItem->vcofins = $itemFilter->sum('vcofins');
                $uItem->vbccofins = $itemFilter->sum('vbccofins');
                $this->addChildren(['RegC501', 'RegC505'], [$uItem, $uItem]);
            }
            $this->setGenericError("NF Numero " . $nf->nfnumero);
            $this->addReg($this);
        }
        return $this;
    } 

    protected function getValidationArray()
    {
        return [
            'COD_PART'  => static::getBaseVR("Código do participante do fornecedor", 60),
            'COD_MOD'   => static::getBaseVR("Código do modelo do documento fiscal", 2, true),
            'COD_SIT'   => static::getBaseVR("Código da situação do documento fiscal", 2, true, "O",  ['00', '01', '02', '03', '06', '07', '08']),
            'SER'       => static::getBaseVR("Série do documento fiscal", 4, false, "N"),
            'SUB'       => static::getBaseVR("Subsérie do documento fiscal", 3, false, "N"),
            'NUM_DOC'   => static::getBaseVR("Número do documento fiscal", 9),
            'DT_DOC'    => static::getBaseVR("Data da emissão do documento fiscal", 8, true),
            'DT_ENT'    => static::getBaseVR("Data da entrada", 8, false, "N"),
            'VL_DOC'    => static::getBaseVR("Valor total do documento fiscal", false),
            'VL_ICMS'   => static::getBaseVR("Valor acumulado do ICMS", false, false, "N"),
            'COD_INF'   => static::getBaseVR("Código da informação complementar do documento fiscal", 6, false, "N"),
            'VL_PIS'    => static::getBaseVR("Valor do PIS/PASEP", false, false, "N"),
            'VL_COFINS' => static::getBaseVR("Valor da COFINS", false, false, "N"),
        ];
    }
}