<?php
namespace App\Processors\Sped\Contribuicao\Bloco0;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

/**
* 
*/
class Reg0111 extends AbstractReg
{
    // Receita Bruta Não-Cumulativa - Tributada no Mercado Interno
    protected $REC_BRU_NCUM_TRIB_MI;
    // Receita Bruta Não-Cumulativa – Não Tributada no Mercado Interno (Vendas com suspensão,
    // alíquota zero, isenção e sem incidência das contribuições)
    protected $REC_BRU_NCUM_NT_MI;
    // <summary>
    // Receita Bruta Não-Cumulativa – Exportação
    protected $REC_BRU_NCUM_EXP;
    // Receita Bruta Cumulativa
    protected $REC_BRU_CUM;
    // Receita Bruta Total
    protected $REC_BRU_TOTAL;

    protected function setAttributes($data=[])
    {
        $datainicio = insertDataOracle(explode(' ', $data['datainicio'])[0]);
        $datafim = insertDataOracle(explode(' ', $data['datafim'])[0]);

        $sql = "SELECT SUM(CASE WHEN i.VPIS = 0 THEN i.vprod ELSE 0 END) AS valortributado, "
            . " SUM(CASE WHEN i.VPIS > 0 THEN i.vprod ELSE 0 END) AS valornaotributado "
            . " FROM nfemitidaitems i INNER JOIN nfoperacaos op ON op.id = i.nfoperacao_id "
            . " INNER JOIN NFEMITIDAS NF ON NF.id = i.NFEMITIDA_ID WHERE op.SPEDVENDA = 1 "
            . " AND NF.nfsituacao_id = 100 AND datahoraemissao BETWEEN TO_DATE('$datainicio', 'yyyy-mm-dd') "
            . " AND TO_DATE('$datafim', 'yyyy-mm-dd')";

        $nf = collect(\DB::select($sql))->first();

        $reg0110 = isset($data['allRegs']['Reg0110']) ? $data['allRegs']['Reg0110']->first() : null;

        if(!is_null($reg0110) && ($reg0110->getField('COD_INC_TRIB') == 2 || $reg0110->getField('IND_APRO_CRED') == 1)){
            $this->none = true;
            return $this;
        }

        $this->REC_BRU_NCUM_TRIB_MI = is_null($nf->valortributado) ? 0 : $nf->valortributado ;
        $this->REC_BRU_NCUM_NT_MI = is_null($nf->valornaotributado) ? 0 : $nf->valornaotributado ;
        $this->REC_BRU_NCUM_EXP = 0;
        $this->REC_BRU_CUM = 0;
        $this->REC_BRU_TOTAL = $this->REC_BRU_NCUM_TRIB_MI + $this->REC_BRU_NCUM_NT_MI + $this->REC_BRU_CUM + $this->REC_BRU_NCUM_EXP;

        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'REC_BRU_NCUM_TRIB_MI'  => static::getBaseVR("Receita Bruta Não-Cumulativa - Tributada no Mercado Interno", false),
            'REC_BRU_NCUM_NT_MI'    => static::getBaseVR("Tributada no Mercado Interno (Vendas com suspensão, alíquota zero, isenção e sem incidência das contribuições)", false),
            'REC_BRU_NCUM_EXP'      => static::getBaseVR("Receita Bruta Não-Cumulativa – Exportação", false),
            'REC_BRU_CUM'           => static::getBaseVR("Receita Bruta Cumulativa", false),
            'REC_BRU_TOTAL'         => static::getBaseVR("Receita Bruta Total", false, $this->calls('REC_BRU_TOTAL')),
        ];
    }

    protected function calls($index)
    {
        $col = collect([
            'REC_BRU_TOTAL' => function ($value) {
                $soma = $this->REC_BRU_NCUM_TRIB_MI + $this->REC_BRU_NCUM_NT_MI + $this->REC_BRU_NCUM_EXP + $this->REC_BRU_CUM;
                if ($value != $some) {
                    $this->addError("Receita Bruta total difere da soma das Receitas.");
                }
            }
        ]);

        $col->get($index);
    }
}
