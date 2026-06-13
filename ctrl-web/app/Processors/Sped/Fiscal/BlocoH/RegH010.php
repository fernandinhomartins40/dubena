<?php

namespace App\Processors\Sped\Fiscal\BlocoH;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

class RegH010 extends AbstractReg
{
    /// Código do item (campo 02 do Registro 0200)
    public $COD_ITEM;
    /// Unidade do item (Campo 02 do registro 0190)
    public $UNID;
    /// Quantidade do item
    public $QTD;
    /// Valor unitário do item (mercadorias ou serviços)
    public $VL_UNIT;
    /// Valor total do item (mercadorias ou serviços)
    public $VL_ITEM;
    /// Indicador de propriedade/posse do item:
    /// 0- Item de propriedade do informante e em seu poder;
    /// 1- Item de propriedade do informante em posse de terceiros;
    /// 2- Item de propriedade de terceiros em posse do informante
    public $IND_PROP;
    /// Código do participante (campo 02 do Registro 0150):
    ///     - do emitente do documento, no caso de emissão de terceiros;
    ///     - do adquirente, no caso de serviços prestados.
    public $COD_PART;
    /// Descrição complementar
    public $TXT_COMPL;
    /// Código da conta analítica contábil debitada/creditada
    public $COD_CTA;
    public $VL_ITEM_IR;

    protected function setAttributes($data = [])
    {
        $datainicio = Util::dateToUS($data["datainicio"]);
        $datafim = Util::dateToUS($data['datafim']);

        $inventarios = \DB::table('inventarios')
                ->join('inventarioitems', 'inventarioitems.inventario_id', '=', 'inventarios.id')
                ->join('produtos', 'produtos.id', '=', 'inventarioitems.produto_id')
                ->join('unidademedidas', 'unidademedidas.id', '=', 'produtos.unidademedida_id')
                ->select(\DB::raw("produtos.id as produto_id, inventarioitems.quantidade, "
                                . "unidademedidas.sigla, inventarioitems.valorunitario, "
                                . "(inventarioitems.quantidade * inventarioitems.valorunitario) as valortotal "))
                ->where('inventarios.empresa_id', \Session::get('empresa_padrao')->id)
                ->whereBetween('inventarios.mesentrega', [$datainicio, $datafim]);

        $results = $inventarios->get();

        foreach ($results as $rowSped) {
            $this->COD_ITEM = $rowSped->produto_id;
            $this->UNID = $rowSped->sigla;
            $this->QTD = Util::numberFormat($rowSped->quantidade, 3);
            $this->VL_UNIT = Util::numberFormat($rowSped->valorunitario, 3);
            $this->VL_ITEM = Util::numberFormat($rowSped->valortotal, 2);
            $this->IND_PROP = "0";
            $this->COD_PART = "";
            $this->TXT_COMPL = "";
            $this->COD_CTA = "";
            $this->addReg($this);
        }

        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'COD_ITEM'   => static::getBaseVR("Código do item"),
            'UNID'       => static::getBaseVR("Unidade do item"),
            'QTD'        => static::getBaseVR("Quantidade do item"),
            'VL_UNIT'    => static::getBaseVR("Valor unitário do item"),
            'VL_ITEM'    => static::getBaseVR("Valor do item"),
            'IND_PROP'   => static::getBaseVR("Indicador de propriedade/posse do item", 1, true, "O", [0, 1, 2]),
            'COD_PART'   => static::getBaseVR("Código do participante"),
            'TXT_COMPL'  => static::getBaseVR("Descrição complementar"),
            'COD_CTA'    => static::getBaseVR("Código da conta analítica contábil debitada/creditada"),
            'VL_ITEM_IR' => static::getBaseVR("Valor do item para efeitos do Imposto de Renda"),
        ];
    }

}
