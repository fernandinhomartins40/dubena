<?php

namespace App\Processors\Sped\Fiscal\BlocoC;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;
use Session;

/**
 * Class RegC350
 *  <i>usada para nf consumidor 02 de emissão própria</i>
 * @package App\Processors\Sped\Fiscal\BlocoC
 */
class RegC350 extends AbstractReg
{

    public $vlrTotICMSCredito;
    public $vlrTotICMSDebito;
    public $vlrTotICMSCreditoST;
    public $vlrTotICMSDebitoST;
    /// Indicador do tipo de operação:
    ///     0 - Serviço Contratado pelo Estabelecimento;
    ///     1 - Serviço Prestado pelo Estabelecimento.
    /// Série do documento fiscal
    public $SER;
    /// Sub Série do documento fiscal
    public $SUB;
    /// Número do documento fiscal ou documento internacional equivalente
    public $NUM_DOC;
    /// Data da emissão do documento fiscal
    public $DT_DOC;
    /// CNPJ/CPF
    public $CNPJ_CPF;
    /// Valor total do documento
    public $VL_MERC;
    /// Valor total do documento
    public $VL_DOC;
    /// Valor total do desconto
    public $VL_DESC;
    /// Valor do PIS
    public $VL_PIS;
    /// Valor do COFINS
    public $VL_COFINS;
    /// Código da conta analítica contábil debitada/creditada
    public $COD_CTA;

    protected function setAttributes($data = [])
    {
        $notas = $data['nf']->filter(function ($nf) {
                    return ($nf->tiponf == 'emitida' && $nf->nfsituacao_id != 102) ||
                            ($nf->tiponf == 'recebida' && Util::hasIn($nf->nfmodelo, ['01', '1B', '04', '55', '65']));
                })->unique('nf_id');

        if ($notas->count() === 0) {
            $this->none = true;
        }

        foreach ($notas as $nf) {

            $this->SER = $nf->nfserie;
            $this->SUB = $nf->nfsubserie;
            $this->NUM_DOC = $nf->nfnumero;
            $this->DT_DOC = Util::dateFormat($nf->datahoraemissao);
            $this->CNPJ_CPF = Util::pregReplaceCnpjCpf($nf->destcpf ? $nf->destcpf : $nf->destcnpj);
            $this->VL_MERC = Util::numberFormat($nf->vprodnf, 2);
            $this->VL_DOC = Util::numberFormat($nf->vnf);
            $this->VL_DESC = Util::numberFormat($nf->vdescnf);
            $this->VL_PIS = Util::numberFormat($nf->vpisnf);
            $this->VL_COFINS = Util::numberFormat($nf->vcofinsnf);
            $this->COD_CTA = $nf->naturezasped;

            //Itens
            $i = 1;
            $items = $notas->where('nf_id', $nf->nf_id);
            foreach ($items as $item) {
                $item->index = $i++;
                if ($nf->tipo == 1) {
                    $this->addChildren('RegC370', $item);
                }
                
                $this->addChildren('RegC390', $item);

                if ((Util::hasIn(substr($item->cfop, 0, 1), ["5-6-7"]) && !trim($item->cfop) == "5605") || trim($item->cfop) == "1605") {
                    $this->vlrTotICMSDebito += $item->vicms;
                    $this->vlrTotICMSDebitoST += $item->vicmsst;
                }
                if ((Util::hasIn(substr($item->cfop, 0, 1), ["1-2-3"]) && !trim($item->cfop) == "1605") || trim($item->cfop) == "5605") {
                    $this->vlrTotICMSCredito += $item->vicms;
                    $this->vlrTotICMSCreditoST += $item->vicmsst;
                }
            }

            $this->addReg($this);
        }

        return $this;
    }

    protected function getValidationArray()
    {
        return [
            
        ];
    }

}
