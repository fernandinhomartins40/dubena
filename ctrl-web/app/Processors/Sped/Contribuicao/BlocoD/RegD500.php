<?php
namespace App\Processors\Sped\Contribuicao\BlocoD;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;

use App\Processors\Sped\Util;
use App\Empresa;
use Session;
// REGISTRO D500 NOTA FISCAL/CONTA DE ENERGIA ELÉTRICA (CÓDIGO 06),
// NOTA FISCAL/CONTA DE FORNECIMENTO D'ÁGUA CANALIZADA (CÓDIGO 29)
// E NOTA FISCAL CONSUMO FORNECIMENTO DE GÁS (CÓDIGO 28)
class RegD500 extends AbstractReg
{
    // Indicador do tipo de operação:
    //     0 - Serviço Contratado pelo Estabelecimento;
    //     1 - Serviço Prestado pelo Estabelecimento.
    protected $IND_OPER;
    // Indicador do emitente do documento fiscal:
    //     0 - Emissão própria;
    //     1 - Emissão de Terceiros
    protected $IND_EMIT;
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
    // Data da entrada
    protected $DT_A_P;
    // Valor total do documento
    protected $VL_DOC;
    // Valor total do desconto
    protected $VL_DESC;
    // Valor da prestação de serviço
    protected $VL_SERV;
    // Valor total dos serviços não-tributados pelo ICMS
    protected $VL_SERV_NT;
    // Valores cobrados em nome de terceiros
    protected $VL_TERC;
    // Valor de outras despesas indicadas no documento fiscal
    protected $VL_DA;
    // Valor da base de cálculo do ICMS
    protected $VL_BC_ICMS;
    // Valor do ICMS
    protected $VL_ICMS;
    // Código da informação complementar do documento fiscal (campo 02 do Registro 0450)
    protected $COD_INF;
    // Valor total do PIS
    protected $VL_PIS;
    // Valor total da COFINS
    protected $VL_COFINS;

    public function __construct($path, $data)
    {
        $this->retornaMultiplo = true;
        $this->regs = [];
        parent::__construct($path, $data);
    }

    protected function layout()
    {
        return $this;
    }
    
    protected function setAttributes($data = [])
    {
        $notas = $data['nf']->filter(function ($nf)
        {
            return Util::hasIn($nf->nfmodelo, ['21','22']) && $nf->tiponf == "recebida";
        })->unique('nf_id');

        if($notas->count() === 0)
            $this->none = true;
        
        foreach ($notas as $nf) {
            $this->IND_OPER = $nf->tipo;
            $this->IND_EMIT = $nf->tiponf == 'emitida' ? 0 : 1;
            $this->COD_PART = $nf->cliente_id;
            $this->COD_MOD = $nf->nfmodelo;
            if ($nf->nfsituacao_id == "100")
                $this->COD_SIT = "00";
            else if ($nf->nfsituacao_id == "101")
                $this->COD_SIT = "02";
            else
                $this->COD_SIT = "99";

            $this->COD_INF = "";
            $this->DT_DOC = Util::dateFormat($nf->datahoraemissao);
            $this->DT_A_P = Util::dateFormat($nf->datahoraentradasaida);
            $this->SER = $nf->nfserie;
            $this->SUB = $nf->nfsubserie;
            $this->NUM_DOC = $nf->nfnumero;
            $this->VL_DOC = Util::numberFormat($nf->vnf); # VNF agora possui o Valor ja com o desconto
            $this->VL_DESC = Util::numberFormat($nf->vdesc);
            $this->VL_SERV = Util::numberFormat($nf->vprod);
            $this->VL_SERV_NT = '0,00';
            $this->VL_TERC = '0,00';
            $this->VL_DA = '0,00';
            $this->VL_BC_ICMS = Util::numberFormat($nf->vbc);
            $this->VL_ICMS = Util::numberFormat($nf->vicms);
            $this->VL_PIS = Util::numberFormat($nf->vpis);
            $this->VL_COFINS = Util::numberFormat($nf->vcofins);


            $items = $data['nf']->filter(function ($item) use ($nf)
            {
                return $item->nf_id == $nf->nf_id;
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
                $this->addChildren(['RegD501', 'RegD501'], [$uItem, $uItem]);
            }
            $this->setGenericError("NF numero" . $nf->nfnumero);
            $this->addReg($this);
        }        

        return $this;
    } 
    
    protected function getValidationArray()
    {
        return [
            'IND_OPER'      => static::getBaseVR("Indicador do tipo de operação", 1, true),
            'IND_EMIT'      => static::getBaseVR("Indicador do emitente do documento fiscal", 1, true, "O",  ['0', '1']),
            'COD_PART'      => static::getBaseVR("Código do participante prestador do serviço", 60),
            'COD_MOD'       => static::getBaseVR("Código do modelo do documento fiscal", 2, true, "O", ['21', '22']),
            'COD_SIT'       => static::getBaseVR("Çódigo da situação do documento fiscal", 2, true, "O",  ['00', '01', '02', '03', '06', '07', '08']),
            'SER'           => static::getBaseVR("Série do documento fiscal", 4, false, "N"),
            'SUB'           => static::getBaseVR("Subsérie do documento fiscal", 3, false, "N"),
            'NUM_DOC'       => static::getBaseVR("Número do documento fiscal", 9),
            'DT_DOC'        => static::getBaseVR("Data da emissão do documento fiscal", 8, true),
            'DT_A_P'        => static::getBaseVR("Data da entrada (aquisição)", 8, true),
            'VL_DOC'        => static::getBaseVR("Valor total do documento fiscal", false),
            'VL_DESC'       => static::getBaseVR("Valor total do desconto", false, false, "N"),
            'VL_SERV'       => static::getBaseVR("Valor da prestação de serviços", false),
            'VL_SERV_NT'    => static::getBaseVR("Valor total dos serviços não-tributados pelo ICMS", false, false, "N"),
            'VL_TERC'       => static::getBaseVR("Valores cobrados em nome de terceiros", false, false, "N"),
            'VL_DA'         => static::getBaseVR("Valor de outras despesas indicadas no documento fiscal", false, false, "N"),
            'VL_BC_ICMS'    => static::getBaseVR("Valor da base de cálculo do ICMS", false, false, "N"),
            'VL_ICMS'       => static::getBaseVR("Valor do ICMS", false, false, "N"),
            'COD_INF'       => static::getBaseVR("Código da informação complementar", 6, false, "N"),
            'VL_PIS'        => static::getBaseVR("Valor do PIS/PASEP", false, false, "N"),
            'VL_COFINS'     => static::getBaseVR("Valor da COFINS", false, false, "N"),
        ];
    }
}
