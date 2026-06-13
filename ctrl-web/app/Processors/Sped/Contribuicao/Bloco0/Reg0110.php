<?php
namespace App\Processors\Sped\Contribuicao\Bloco0;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;
use App\Empresa;
use Session;

class Reg0110 extends AbstractReg
{

    // Código indicador da incidência tributária no período:
    //     1 – Escrituração de operações com incidência exclusivamente no regime não-cumulativo;
    //     2 – Escrituração de operações com incidência exclusivamente no regime cumulativo;
    //     3 – Escrituração de operações com incidência nos regimes não-cumulativo e cumulativo.
    protected $COD_INC_TRIB;
    // Código indicador de método de apropriação de créditos comuns, no caso de incidência no regime nãocumulativo
    //     (COD_INC_TRIB = 1 ou 3):
    //     1 – Método de Apropriação Direta;
    //     2 – Método de Rateio Proporcional (Receita Bruta)
    protected $IND_APRO_CRED;
    // Código indicador do Tipo de Contribuição Apurada no Período
    //      1 – Apuração da Contribuição Exclusivamente a Alíquota Básica
    //      2 – Apuração da Contribuição a Alíquotas Específicas
    //      (Diferenciadas e/ou por Unidade de Medida de Produto)
    protected $COD_TIPO_CONT;
    // Código indicador do critério de escrituração e apuração adotado, no caso de incidência exclusivamente no
    // regime cumulativo (COD_INC_TRIB = 2), pela pessoa jurídica submetida ao regime de tributação com base no
    // lucro presumido:
    // 1 – Regime de Caixa – Escrituração consolidada (Registro F500);
    // 2 – Regime de Competência - Escrituração consolidada (Registro F550);
    // 9 – Regime de Competência - Escrituração detalhada, com base nos registros dos Blocos “A”, “C”, “D” e “F”.             // </summary>
    protected $IND_REG_CUM;

    protected function setAttributes($data = [])
    {
        $this->COD_INC_TRIB = $data['empresa']->spedincidenciatributaria;
        $this->IND_APRO_CRED = $data['empresa']->spedapropriacaocredito;
        $this->COD_TIPO_CONT = $data['empresa']->spedtipocontribuicao;
        $this->IND_REG_CUM = $this->COD_INC_TRIB == '2' ? $data['empresa']->spedregimecumulativo : "";

        $this->setGenericError("Empresa");
        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'COD_INC_TRIB'  => static::getBaseVR("Código indicador da incidência tributária no período", 1, true, "O", [1, 2, 3]),
            'IND_APRO_CRED' => static::getBaseVR("Código indicador de método de apropriação de créditos comuns", 1, true, "N", [1, 2]),
            'COD_TIPO_CONT' => static::getBaseVR("Código indicador do Tipo de Contribuição Apurada no Período", 1, true, "N", [1, 2]),
            'IND_REG_CUM'   => static::getBaseVR("Código indicador do critério de escrituração e apuração adotado", 1, true, "N", [1, 2, 9]),
        ];
    }
}
