<?php

namespace App\Processors\Sped\Contribuicao\Bloco0;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;
use App\Processors\Sped\Util;
use App\Empresa;
use Session;

/**
 * 
 */
class Reg0000 extends AbstractReg
{

    // Tipo de escrituração:
    //     0 - Original;
    //     1 – Retificadora.
    protected $TIPO_ESCRIT;
    // Versão do Leiaute
    protected $COD_VER;
    // Indicador de situação especial:
    //     0 - Abertura;
    //     1 - Cisão;
    //     2 - Fusão;
    //     3 - Incorporação;
    //     4 - Encerramento;
    protected $IND_SIT_ESP;
    // Número do Recibo da Escrituração anterior a ser retificada, 
    // utilizado quando TIPO_ESCRIT for igual a 1
    protected $NUM_REC_ANTERIOR;
    // Data inicial das informações contidas no arquivo
    protected $DT_INI;
    // Data final das informações contidas no arquivo
    protected $DT_FIN;
    // Nome da Empresa no ERP
    protected $NOME;
    // Número de inscrição do estabelecimento matriz da
    // pessoa jurídica no CNPJ.
    protected $CNPJ;
    // Sigla da Unidade da Federação da pessoa jurídica
    protected $UF;
    // Código do município do domicílio fiscal da pessoa jurídica, conforme a tabela IBGE
    protected $COD_MUN;
    // Inscrição da pessoa jurídica na Suframa
    protected $SUFRAMA;
    // Indicador da natureza da pessoa jurídica:
    //     00 – Sociedade empresária em geral
    //     01 – Sociedade cooperativa
    //     02 – Entidade sujeita ao PIS/Pasep exclusivamente
    //     com base na Folha de Salários
    protected $IND_NAT_PJ;
    // Indicador de tipo de atividade preponderante:
    //     0 – Industrial ou equiparado a industrial;
    //     1 – Prestador de serviços;
    //     2 - Atividade de comércio;
    //     3 – Atividade financeira;
    //     4 – Atividade imobiliária;
    //     9 – Outros.
    protected $IND_ATIV;

    protected function setAttributes($data = [])
    {
        $empresa = Empresa::with('cidade', 'rua', 'bairro')
                        ->select('cidade_id', 'razao_social', 'cnpj', 'uf', 'spedatividade',
                                'bairro_id', 'rua_id', 'suframa')
                        ->where('id', Session::get('empresa_padrao')->id)->get()->first();
        $this->COD_VER = '003';
        $this->TIPO_ESCRIT = strtoupper($data['tipoescrit']);
        $this->IND_SIT_ESP = '';
        $this->NUM_REC_ANTERIOR = $data['tipoescrit'] == "1" ? $data['reciboanterior'] : "";
        $this->DT_INI = Util::dateFormat($data['datainicio']);
        $this->DT_FIN = Util::dateFormat($data['datafim']);
        $this->NOME = Util::replaceAccent($empresa->razao_social);        
        $this->CNPJ = Util::pregReplaceCnpjCpf($empresa->cnpj);
        $this->UF = $empresa->uf;
        $this->COD_MUN = $empresa->cidade->cod_ibge;
        $this->SUFRAMA = $empresa->suframa;
        $this->IND_NAT_PJ = "00";
        $this->IND_ATIV = $empresa->spedatividade;

        $this->setGenericError("Empresa");
        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'COD_VER'           => static::getBaseVR("Código da versão do leiaute", 3, true),
            'TIPO_ESCRIT'       => static::getBaseVR("Tipo de escrituração", 1, true, "O", [0, 1]),
            'IND_SIT_ESP'       => static::getBaseVR("Indicador de situação especial", 1, true, "N", [0, 1, 2, 3, 4]),
            'NUM_REC_ANTERIOR'  => static::getBaseVR("Número do recibo da escrituração anterior a ser retificado", 41, true, 'N'),
            'DT_INI'            => static::getBaseVR("Data Inicial das informações", 8, true, "O", $this->calls("DT_INI")),
            'DT_FIN'            => static::getBaseVR("Data Final das informaçãoes", 8, true, "O", $this->calls("DT_FIN")),
            'NOME'              => static::getBaseVR("Nome empresarial da pessoa jurídica ", 100),
            'CNPJ'              => static::getBaseVR("CNPJ", 14, true),
            'UF'                => static::getBaseVR("Sigla da Unidade da Federação da pessoa jurídica", 2, true),
            'COD_MUN'           => static::getBaseVR("Código do município", 7, true),
            'SUFRAMA'           => static::getBaseVR("Inscrição da pessoa jurídica na Suframa", 9, true, "N"),
            'IND_NAT_PJ'        => static::getBaseVR("Indicador da natureza da pessoa jurídica", 2, true, "N", [00, 01, 02, 03, 04, 05]),
            'IND_ATIV'          => static::getBaseVR("Indicador de tipo de atividade preponderante", 1, false, "O", [0, 1, 2, 3, 4, 9]),
        ];
    }

    private function calls($index)
    {
        $col = collect([
            "DT_INI"    => function ($value) {
                if (! (Util::getMonth($value) == Util::getMonth($this->DT_FIN)) || ! (Util::getYear($value) == Util::getYear($this->DT_FIN)))
                    $this->addError("Datas de Início e Fim devem pertecer ao mesmo ano/mes.");
            },
            "DT_FIN"    => function ($value) {
                if (! (Util::getMonth($this->DT_INI) == Util::getMonth($value)) || ! (Util::getYear($this->DT_INI) == Util::getYear($value)))
                    $this->addError("Datas de Início e Fim devem pertecer ao mesmo ano/mes.");
            }
        ]);

        return $col->get($index);
    }
}
