<?php
namespace App\Processors\Sped\Contribuicao\Bloco0;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

class Reg0500 extends AbstractReg
{
    // Data da inclusão/alteração
    protected $DT_ALT;
    // Código da natureza da conta/grupo de contas:
    //     01 - Contas de ativo
    //     02 - Contas de passivo;
    //     03 - Patrimônio líquido;
    //     04 - Contas de resultado;
    //     05 - Contas de compensação;
    //     09 - Outras.
    protected $COD_NAT_CC;
    // Indicador do tipo de conta:
    //     S - Sintética (grupo de contas);
    //     A - Analítica (conta).
    protected $IND_CTA;
    // Nível da conta analítica/grupo de contas
    protected $NIVEL;
    // Código da conta analítica/grupo de contas.
    protected $COD_CTA;
    // Nome da conta analítica/grupo de contas
    protected $NOME_CTA;
    // Código da conta correlacionada no Plano de Contas
    // Referenciado, publicado pela RFB
    protected $COD_CTA_REF;
    // CNPJ do estabelecimento, no caso da conta informada no 
    // campo COD_CTA ser específica de um estabelecimento.
    protected $CNPJ_EST;

    protected function setAttributes($data = [])
    {
        $notas = $data['nf']->unique(function ($nf) {
            return $nf->planocontadata . $nf->planoconta_id;
        });

        foreach ($notas as $nf) {
            $this->DT_ALT = Util::dateFormat($nf->planocontadata);
            $this->COD_NAT_CC = strlen($nf->naturezasped) == 1 ? "0" . $nf->naturezasped : $nf->naturezasped;
            $this->IND_CTA = $nf->pcfinalizador ? "S" : "A";
            $this->NIVEL = $nf->pcnivel;
            $this->COD_CTA = $nf->planoconta_id;
            $this->NOME_CTA = $nf->planocontadescricao;
            $this->COD_CTA_REF = "";
            $this->CNPJ_EST = "";
            $this->setGenericError("NF Plano Conta " . $nf->planoconta_id);
            $this->addReg($this);
        }
        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'DT_ALT'        => static::getBaseVR("Data da inclusão/alteração", 8, true),
            'COD_NAT_CC'    => static::getBaseVR("Código da natureza da conta/grupo de contas", 2, true, "O",  ['01', '02', '03', '04', '05', '09']),
            'IND_CTA'       => static::getBaseVR("Indicador do tipo de conta", 1, true, "O", ["S", "A"]),
            'NIVEL'         => static::getBaseVR("Nível da conta analítica/grupo de contas", 5),
            'COD_CTA'       => static::getBaseVR("Código da conta analítica/grupo de contas", 255),
            'NOME_CTA'      => static::getBaseVR("Nome da conta analítica/grupo de contas", 60),
            'COD_CTA_REF'   => static::getBaseVR("Código da conta correlacionada no Plano de Contas Referenciado", 60, false, "N"),
            'CNPJ_EST'      => static::getBaseVR("CNPJ do estabelecimento", 14, true, "N")
        ];
    }
}
