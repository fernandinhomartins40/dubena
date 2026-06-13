<?php

namespace App\Processors\Sped\Fiscal\Bloco0;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

/**
 * 
 */
class Reg0000 extends AbstractReg
{

    /**
     * Código da versão do leiaute conforme a tabela indicada no Ato COTEPE. 
     * @var mixed
     */
    protected $COD_VER;

    /**
     * 0 - Remessa do arquivo original;
     * 1 - Remessa do arquivo substituto
     * @var mixed 
     */
    public $COD_FIN;

    /**
     *
     * Data inicial das informações contidas no arquivo.
     * @var mixed
     */
    protected $DT_INI;

    /**
     * Data final das informações contidas no arquivo
     * @var mixed 
     */
    protected $DT_FIN;

    /**
     * Nome empresarial da entidade.
     * @var mixed
     */
    protected $NOME;

    /**
     * Número de inscrição da entidade no CNPJ.
     * @var mixed
     */
    protected $CNPJ;

    /**
     * Número de inscrição da entidade no CPF.
     * @var mixed 
     */
    protected $CPF;

    /**
     * Sigla da unidade da federação da entidade
     * @var mixed 
     */
    protected $UF;

    /**
     * Inscrição Estadual da entidade. 
     * @var mixed 
     */
    protected $IE;

    /**
     * Código do município do domicílio fiscal da entidade, conforme a tabela IBGE
     * @var mixed 
     */
    protected $COD_MUN;

    /**
     * Inscrição Municipal da entidade
     * @var mixed 
     */
    protected $IM;

    /**
     * Inscrição da entidade na SUFRAMA
     * @var mixed 
     */
    protected $SUFRAMA;

    /**
     * Perfil de apresentação do arquivo fiscal;
     * A – Perfil A;
     * B – Perfil B;
     * C – Perfil C
     * @var mixed 
     */
    protected $IND_PERFIL;

    /*
     * Indicador de tipo de atividade:
     * 0 – Industrial ou equiparado a industrial;
     * 1 – Outros
     * @var mixed 
     */
    protected $IND_ATIV;

    /**
     * retorna o array das validações dos campos
     */
    protected function getValidationArray()
    {
        return [
            'COD_VER'    => static::getBaseVR("Código da versão", 3, true, "O"),
            'COD_FIN'    => static::getBaseVR("Código da finalidade", 1, true, "O", [0, 1]),
            'DT_INI'     => static::getBaseVR("Data inicial", 8, true, "O"),
            'DT_FIN'     => static::getBaseVR("Data final", 8, true, "O"),
            'NOME'       => static::getBaseVR("Nome da empresa", 100, false, "O"),
            'CNPJ'       => static::getBaseVR("CNPJ da empresa", 14, true, "OC"),
            'CPF'        => static::getBaseVR("CPF da empresa", 11, true, "OC"),
            'UF'         => static::getBaseVR("UF da empresa", 2, true, "O"),
            'IE'         => static::getBaseVR("Inscrição Estadual da empresa", 14, false, "O"),
            'COD_MUN'    => static::getBaseVR("Código do município da empresa", 7, true, "O"),
            'IM'         => static::getBaseVR("Inscrição Municipal da empresa", false, true, "OC"),
            'SUFRAMA'    => static::getBaseVR("SUFRAMA da empresa", 9, true, "OC"),
            'IND_PERFIL' => static::getBaseVR("Perfil de apresentação", 1, true, "O", ['A', 'B', 'C']),
            'IND_ATIV'   => static::getBaseVR("Indicador de tipo de atividade", 1, true, "O", [0, 1])
        ];
    }

    /**
     * validações não genericas
     * @return $this
     */
    protected function validaRegistro()
    {
        if (!Util::isEqualsMonthYear($this->DT_INI, $this->DT_FIN))
            $this->addError("Data Inicial e Final devem pertecer ao mesmo ano/mês.");

        if (!Util::validateCNPJ($this->CNPJ))
            $this->addError("CNPJ da Empresa inválido.");

        return $this;
    }

    /**
     * seta os atributos da classe
     * @param array $data
     * @return $this
     */
    protected function setAttributes($data = [])
    {
        $empresa = $data['empresa'];

        $this->COD_VER = "012";

//        if (Util::createDate($data['datafim']) > Util::createDate('31/01/2015')) {
//            $this->COD_VER = "009";
//        }

        $this->COD_FIN = $data['tipoescrit'];
        $this->DT_INI = Util::dateFormat($data['datainicio']);
        $this->DT_FIN = Util::dateFormat($data['datafim']);
        $this->NOME = Util::replaceAccent($empresa->razao_social);
        $this->CNPJ = Util::replaceSpecialChars($empresa->cnpj, true, true);
        $this->CPF = "";
        $this->UF = Util::replaceAccent($empresa->uf);
        $this->IE = Util::replaceSpecialChars($empresa->inscricao_estadual, true, true);
        $this->COD_MUN = $empresa->cidade->cod_ibge;
        $this->IM = Util::replaceSpecialChars($empresa->inscricao_municipal, true, true);
        $this->SUFRAMA = $empresa->suframa;
        $this->IND_ATIV = $empresa->spedatividade;
        $this->IND_PERFIL = $empresa->spedperfil;

        return $this;
    }

}
