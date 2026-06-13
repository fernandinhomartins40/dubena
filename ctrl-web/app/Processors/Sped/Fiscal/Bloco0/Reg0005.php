<?php

namespace App\Processors\Sped\Fiscal\Bloco0;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

/**
 * 
 */
class Reg0005 extends AbstractReg
{

    /**
     * Nome de fantasia associado ao nome empresarial. 
     * @var mixed 
     */
    protected $FANTASIA;

    /**
     * Código de Endereçamento Postal. 
     * @var mixed 
     */
    protected $CEP;

    /**
     * Logradouro e endereço do imóvel.
     * @var mixed 
     */
    protected $END;

    /**
     * Número do imóvel.
     * @var mixed 
     */
    protected $NUM;

    /**
     * Dados complementares do endereço
     * @var mixed 
     */
    protected $COMPL;

    /**
     * Bairro em que o imóvel está situado
     * @var mixed 
     */
    protected $BAIRRO;

    /**
     * Número do telefone (DDD+FONE)
     * @var mixed 
     */
    protected $FONE;

    /**
     * Número do fax
     * @var mixed 
     */
    protected $FAX;

    /**
     * Endereço do correio eletrônico
     * @var mixed 
     */
    protected $EMAIL;

    /**
     * retorna o array das validações dos campos
     */
    protected function getValidationArray()
    {
        return [
            'FANTASIA' => static::getBaseVR("Nome de fantasia da empresa", 60, false, "O"),
            'CEP'      => static::getBaseVR("CEP da empresa", 8, true, "O"),
            'END'      => static::getBaseVR("Endereço da empresa", 60, false, "O"),
            'NUM'      => static::getBaseVR("Número do imóvel da empresa", 10, false, "OC"),
            'COMPL'    => static::getBaseVR("Complemento do endereço", 60, false, "OC"),
            'BAIRRO'   => static::getBaseVR("Bairro da empresa", 60, false, "O"),
            'FONE'     => static::getBaseVR("Telefone da empresa", 11, false, "OC"),
            'FAX'      => static::getBaseVR("Fax da empresa", 11, false, "OC"),
            'EMAIL'    => static::getBaseVR("E-mail da empresa", false, false, "OC")
        ];
    }

    /**
     * validações não genericas
     * @return $this
     */
    protected function validaRegistro()
    {
        if (Util::isNullOrEmpty($this->FANTASIA))
            $this->addError("Nome Fantasia da Empresa inválido.");

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

        $this->FANTASIA = Util::replaceAccent($empresa->razao_social);
        $this->CEP = Util::replaceSpecialChars($empresa->cep, true, true);
        $this->END = Util::replaceAccent($empresa->rua->descricao);
        $this->NUM = Util::replaceSpecialChars($empresa->numero, true, true);
        $this->COMPL = Util::replaceAccent($empresa->complemento);
        $this->BAIRRO = Util::replaceAccent($empresa->bairro->descricao);
        $this->FONE = Util::replaceSpecialChars($empresa->telefone1, true, true);
        $this->FAX = "";
        $this->EMAIL = $empresa->email;

        return $this;
    }

}
