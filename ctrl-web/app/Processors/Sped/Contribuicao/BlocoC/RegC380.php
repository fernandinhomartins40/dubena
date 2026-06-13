<?php
namespace App\Processors\Sped\Contribuicao\BlocoC;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;

use App\Processors\Sped\Util;
use App\Empresa;
use Session;
// REGISTRO C380: CONSOLIDAÇÃO DE NOTAS FISCAIS ELETRÔNICAS EMITIDAS PELA PESSOA JURÍDICA (CÓDIGO 55) – OPERAÇÕES DE VENDAS
class RegC380 extends AbstractReg
{
    // Texto fixo contendo "02" (Código da Nota Fiscal Consumidor, modelo 02, conforme a Tabela 4.1.1)
    protected $COD_MOD;
    // Data de Emissão Inicial dos Documentos
    protected $DT_DOC_INI;
    // Data de Emissão Final dos Documentos
    protected $DT_DOC_FIN;
    // Código do Item (campo 02 do Registro 0200)
    protected $NUM_DOC_INI;
    // Código da Nomenclatura Comum do Mercosul 
    protected $NUM_DOC_FIN;
    // Valor Total do Item
    protected $VL_DOC;
    // Valor Total do Item cancelado
    protected $VL_DOC_CANC;
    // Armazena os Registros Filhos

    protected function setAttributes($data = [])
    {
        $notas = $data['nf']->filter(function ($nf) {
            return $nf->nfmodelo == '02' && $nf->tipo == "1" && $nf->tiponf == 'recebida';
        });
        
        if($notas->count() === 0)
            $this->none = true;

        $this->COD_MOD = "02";
        $this->DT_DOC_INI = Util::dateFormat($notas->min('datahoraentradasaida'));
        $this->DT_DOC_FIN = Util::dateFormat($notas->max('datahoraentradasaida'));
        $this->NUM_DOC_INI = $notas->min('nfnumero');
        $this->NUM_DOC_FIN = $notas->max('nfnumero');
        $this->VL_DOC = Util::numberFormat($notas->where('nfsituacao_id', 1)->sum('vprod'));
        $this->VL_DOC_CANC = Util::numberFormat($notas->where('nfsituacao_id', '!=', 1)->sum('vprod'));

        $uniqueItemsC381 = $notas->unique(function ($nf) {
            return $nf->cstpis.$nf->cprod.$nf->ppis;
        })->sortBy('item_id');

        foreach ($uniqueItemsC381 as $uItem) {
            $itemFilter = $notas->filter(function ($i) use($uItem) {
                return $i->cstpis === $uItem->cstpis && $i->cprod === $uItem->cprod && $i->ppis === $uItem->ppis;
            });
            $uItem->vprod = $itemFilter->sum('vprod');
            $uItem->vpis = $itemFilter->sum('vpis');
            $uItem->vbcpis = $itemFilter->sum('vbcpis');
            $this->addChildren("RegC381", $uItem);
        }

        $uniqueItemsC385 = $notas->unique(function ($nf) {
            return $nf->cstcofins.$nf->cprod.$nf->pcofins;
        })->sortBy('item_id');

        foreach ($uniqueItemsC385 as $uItem) {
            $itemFilter = $notas->filter(function ($i) use($uItem) {
                return $i->cstcofins === $uItem->cstcofins && $i->cprod === $uItem->cprod && $i->pcofins === $uItem->pcofins;
            });
            $uItem->vprod = $itemFilter->sum('vprod');
            $uItem->vbccofins = $itemFilter->sum('vbccofins');
            $uItem->vcofins = $itemFilter->sum('vcofins');
            $this->addChildren("RegC385", $uItem);
        }
        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'COD_MOD'       => static::getBaseVR("Código do modelo do documento fiscal", 2, true, "O", ['02']),
            'DT_DOC_INI'    => static::getBaseVR("Data de Emissão Inicial dos Documentos", 8, true),
            'DT_DOC_FIN'    => static::getBaseVR("Data de Emissão Final dos Documentos", 8, true),
            'NUM_DOC_INI'   => static::getBaseVR("Número do documento fiscal inicial", 6, false, "N"),
            'NUM_DOC_FIN'   => static::getBaseVR("Número do documento fiscal final", 6, false, "N"),
            'VL_DOC'        => static::getBaseVR("Valor total dos documentos emitidos", false, false),
            'VL_DOC_CANC'   => static::getBaseVR("Valor total dos documentos cancelados", false)
        ];
    }
}
