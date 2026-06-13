<?php

namespace App\Processors\Sped\Fiscal\BlocoH;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

class RegH005 extends AbstractReg
{

    /// Data do Inventário
    public $DT_INV;
    /// Valor total do estoque
    public $VL_INV;
    /// Informe o motivo do Inventário:
    /// 01 – No final no período;
    /// 02 – Na mudança de forma de tributação da mercadoria (ICMS);
    /// 03 – Na solicitação da baixa cadastral, paralisação temporária e outras situações;
    /// 04 – Na alteração de regime de pagamento – condição do contribuinte;
    /// 05 – Por determinação dos fiscos.
    public $MOT_INV;

    protected function setAttributes($data = [])
    {
        $datainicio = Util::dateToUS($data["datainicio"]);
        $datafim = Util::dateToUS($data['datafim']);

        $results = \DB::table('inventarios')
                ->select(\DB::raw("inventarios.id, inventarios.mesentrega, inventarios.datainventario, inventarios.valorinventario "))
                ->where('inventarios.empresa_id', \Session::get('empresa_padrao')->id)
                ->whereBetween('inventarios.mesentrega', [$datainicio, $datafim])->get();

        foreach ($results as $rowSped) {
            $this->DT_INV = Util::dateFormat($rowSped->datainventario);
            $this->MOT_INV = "01";
            $this->VL_INV = $rowSped->valorinventario;
            $this->addReg($this);
        }
        

        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'DT_INV'  => static::getBaseVR("Data do inventário", 8, true),
            'VL_INV'  => static::getBaseVR("Valor total do estoque"),
            'MOT_INV' => static::getBaseVR("Motivo do Inventário", 8, true, "O", ['01', '02', '03', '04', '05'])
        ];
    }

}
