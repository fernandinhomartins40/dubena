<?php

namespace App\Processors\Sped\Fiscal\Bloco0;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

/**
 * 
 */
class Reg0100 extends AbstractReg
{

    /**
     * Nome do contabilista.
     * @var mixed
     */
    public $NOME;

    /**
     * Número de inscrição do contabilista no CPF.
     * @var mixed
     */
    public $CPF;

    /**
     * Número de inscrição do contabilista no Conselho Regional de Contabilidade.
     * @var mixed
     */
    public $CRC;

    /**
     * Número de inscrição do escritório de contabilidade no CNPJ, se houver.
     * @var mixed
     */
    public $CNPJ;

    /**
     * Código de Endereçamento Postal.
     * @var mixed
     */
    public $CEP;

    /**
     * Logradouro e endereço do imóvel
     * @var mixed
     */
    public $END;

    /**
     * Número do imóvel
     * @var mixed
     */
    public $NUM;

    /**
     * Dados complementares do endereço
     * @var mixed
     */
    public $COMPL;

    /**
     * Bairro em que o imóvel está situado
     * @var mixed
     */
    public $BAIRRO;

    /**
     * Número do telefone
     * @var mixed
     */
    public $FONE;

    /**
     * Número do fax
     * @var mixed
     */
    public $FAX;

    /**
     * Endereço do correio eletrônico
     * @var mixed
     */
    public $EMAIL;

    /**
     * Código do município, conforme tabela IBGE.
     * @var mixed
     */
    public $COD_MUN;

    /**
     * retorna o array das validações dos campos
     */
    protected function getValidationArray()
    {
        return [
            'NOME'    => static::getBaseVR("Nome do contabilista", 100, false, "O"),
            'CPF'     => static::getBaseVR("CPF do contabilista", 11, true, "O"),
            'CRC'     => static::getBaseVR("CRC do contabilista", 15, false, "O"),
            'CNPJ'    => static::getBaseVR("CNPJ do escritório de contabilidade", 14, true, "OC"),
            'CEP'     => static::getBaseVR("CEP do contabilista", 8, true, "OC"),
            'END'     => static::getBaseVR("Endereço do escritório de contabilidade", 60, false, 'OC'),
            'NUM'     => static::getBaseVR("Número do escritório de contabilidade", 10, false, "OC"),
            'COMPL'   => static::getBaseVR("Dados complementares do endereço", 60, false, "OC"),
            'BAIRRO'  => static::getBaseVR("Bairro do escritório de contabilidade", 60, false, "OC"),
            'FONE'    => static::getBaseVR("Telefone do contabilista", 11, false, "OC"),
            'FAX'     => static::getBaseVR("Telefone do contabilista", 11, false, "OC"),
            'EMAIL'   => static::getBaseVR("E-mail do contabilista", false, false, "O"),
            'COD_MUN' => static::getBaseVR("Código do município, conforme tabela IBGE", 7, true, 'O')
        ];
    }

    /**
     * validações não genericas
     * @return $this
     */
    protected function validaRegistro()
    {
        if (Util::isNullOrEmpty($this->NOME))
            $this->addError("Nome do contabilista é Obrigatório.");

        if (!Util::validateCPF($this->CPF))
            $this->addError("CPF do Contabilista Inválido.");

        if (Util::isNullOrEmpty($this->CRC))
            $this->addError("CRC do contabilista é Obrigatório.");

        if (!Util::validateCNPJ($this->CNPJ))
            $this->addError("CNPJ do contabilista é Inválido.");

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

        $this->NOME = Util::replaceAccent($empresa->contnome);
        $this->CPF = Util::replaceSpecialChars($empresa->contcpf, true, true);
        $this->CRC = Util::replaceSpecialChars($empresa->contcrc, true, true);
        $this->CNPJ = Util::replaceSpecialChars($empresa->contcnpj, true, true);
        $this->CEP = Util::replaceSpecialChars($empresa->contcep, true, true);
        $this->END = Util::replaceAccent($empresa->contRua->descricao);
        $this->NUM = Util::replaceAccent($empresa->contnumero);
        $this->COMPL = Util::replaceAccent($empresa->contcomplemento);
        $this->BAIRRO = Util::replaceAccent($empresa->contBairro->descricao);
        $this->FONE = Util::replaceSpecialChars($empresa->conttelefone, true, true);
        $this->FAX = Util::replaceSpecialChars($empresa->contfax, true, true);
        $this->EMAIL = $empresa->contemail;
        $this->COD_MUN = $empresa->contCidade->cod_ibge;

        return $this;
    }

}
