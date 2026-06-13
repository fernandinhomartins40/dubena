<?php

namespace App\Processors\Sped\Fiscal\Bloco0;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;
use App\Helpers\Utils\NfUtil;

class Reg0200 extends AbstractReg
{

    /**
     * Código do item
     * @var mixed
     */
    protected $COD_ITEM;

    /**
     * Descrição do item
     * @var mixed
     */
    protected $DESCR_ITEM;

    /**
     * Representação alfanumérico do código de barra do produto, se houver.
     * @var mixed
     */
    protected $COD_BARRA;

    /**
     * Código anterior do item com relação à última informação apresentada.
     * @var mixed
     */
    protected $COD_ANT_ITEM;

    /**
     * Unidade de medida utilizada na quantificação de estoques.
     * @var mixed
     */
    protected $UNID_INV;

    /**
     * Tipo do item – Atividades Industriais, Comerciais e Serviços
     * @var mixed
     */
    protected $TIPO_ITEM;

    /**
     * Código da Nomenclatura Comum do Mercosul
     * @var mixed
     */
    protected $COD_NCM;

    /**
     * Código EX, conforme a TIPI
     * @var mixed
     */
    protected $EX_IPI;

    /**
     * Código do gênero do item, conforme a Tabela 4.2.1.
     * @var mixed
     */
    protected $COD_GEN;

    /**
     * Código do serviço conforme lista do Anexo I da Lei Complementar Federal nº 116/03
     * @var mixed
     */
    protected $COD_LST;

    /**
     * Alíquota de ICMS aplicável ao item nas operações internas
     * @var mixed
     */
    protected $ALIQ_ICMS;

    /**
     * Código Especificador da Substituição Tributária
     * @var mixed
     */
    protected $CEST;

    /**
     * Modelos permitidos a gerar este registro
     * @var array
     */
    protected $models = ['01', '1B', '04', '55', '65'];

    /**
     * retorna o array com as validações genéricas dos campos
     * @return array
     */
    protected function getValidationArray()
    {

        $aTipoItem = ["00", "01", "02", "03", "04", "05", "06", "07", "08", "09", "10", "99"];
        return [
            'COD_ITEM'     => static::getBaseVR("Código do item", 80),
            'DESCR_ITEM'   => static::getBaseVR("Descrição do item", false),
            'COD_BARRA'    => static::getBaseVR("Número do código de barras do produto", false, false, "OC"),
            'COD_ANT_ITEM' => static::getBaseVR("", false, false, "N"),
            'UNID_INV'     => static::getBaseVR("Unidade de medida", 6),
            'TIPO_ITEM'    => static::getBaseVR("Tipo do item", 2, false, "O", $aTipoItem),
            'COD_NCM'      => static::getBaseVR("Código da Nomenclatura Comum do Mercosul", 8, true, "OC"),
            'EX_IPI'       => static::getBaseVR("Código EX, conforme a TIPI", 3, false, "OC"),
            'COD_GEN'      => static::getBaseVR("Código do gênero do item", 2, true, 'OC'),
            'COD_LST'      => static::getBaseVR("Código do serviço conforme lista do Anexo I"
                    . " da Lei Complementar Federal nº 116/03", 5, false, 'OC'),
            'ALIQ_ICMS'    => static::getBaseVR("Alíquota de ICMS", 6, false, "OC"),
            'CEST'         => static::getBaseVR("Código Especificador da Substituição Tributária", 7, true, "OC"),
        ];
    }

    /**
     * seta os atributos da classe
     * @param array $data
     * @return $this
     */
    protected function setAttributes($data = [])
    {
        $unique = function ($nf) {
            return $nf->ucom . $nf->cprod;
        };
        $filter = function ($nf) {
            $models = [
                '21', '22', '01', 'A1', '1B', '04', '55', '65'
            ];
            $allowedE = $nf->tiponf == 'emitida' && NfUtil::isAuthorized($nf->nfsituacao_id);
            $allowedR = $nf->tiponf == 'recebida' && Util::hasIn($nf->nfmodelo, $models);
            return ($allowedE || $allowedR) &&
                    $nf->tiponf == 'recebida'; //adicionado recentemente por conta do regc170
        };
        $produtos = $data['nf']->filter($filter)->unique($unique);

        if ($produtos->count() === 0) {
            $this->none = true;
        }

        foreach ($produtos as $prod) {
            $this->COD_ITEM = $prod->cprod;
            $this->DESCR_ITEM = Util::replaceAccent($prod->produto);
            $this->COD_BARRA = $prod->cean;
            $this->COD_ANT_ITEM = "";
            $this->UNID_INV = $prod->ucom;
            $this->TIPO_ITEM = $prod->nfetipoitem == '12' ? '99' : $prod->nfetipoitem;

            if (strlen($this->TIPO_ITEM) == 1) {
                $this->TIPO_ITEM = "0" . $this->TIPO_ITEM;
            }

            $this->COD_NCM = Util::replaceSpecialChars($prod->ncm, true, true);
            $this->EX_IPI = $prod->nfeextipi;
            $this->COD_GEN = $prod->nfecodgen;
            $this->COD_LST = $prod->nfecodlst;
            $this->ALIQ_ICMS = Util::numberFormat($prod->picms);
            $this->setGenericError("Produto " . $prod->produto);
//            dd($this->aValidation);
            $this->addReg($this);
        }

        return $this;
    }

}
