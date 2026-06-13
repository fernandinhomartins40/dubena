<?php
namespace App\Processors\Sped\Contribuicao\Bloco0;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;
use App\Processors\Sped\Util;
use App\Empresa;
use Session;

class Reg0100 extends AbstractReg
{

    protected $NOME; //Nome do contabilista.
    protected $CPF; //Número de inscrição do contabilista no CPF.
    protected $CRC; // Número de inscrição do contabilista no Conselho Regional de Contabilidade.
    protected $CNPJ; //Número de inscrição do escritório de contabilidade no CNPJ, se houver
    protected $CEP; //Código de Endereçamento Postal.
    protected $END; //Logradouro e endereço do imóvel
    protected $NUM; // Número do imóvel
    protected $COMPL; //Dados complementares do endereço
    protected $BAIRRO; //Bairro em que o imóvel está situado
    protected $FONE; //Número do telefone
    protected $FAX; //Número do fax
    protected $EMAIL; //Endereço do correio eletrônico
    protected $COD_MUN; //Código do município, conforme tabela IBGE.

    // protected function validaRegistro()
    // {
    //     if (Util::isNullOrEmpty($this->NOME))
    //         $this->addError("Nome do contabilista é obrigatório.");

    //     if (!Util::validateCPF($this->CPF))
    //         $this->addError("CPF do Contabilista inválido.");

    //     if (Util::isNullOrEmpty($this->CRC))
    //         $this->addError("CRC do contabilista é obrigatório.");

    //     if (!Util::validateCNPJ($this->CNPJ))
    //         $this->addError("CNPJ do contabilista é inválido.");
            
    //     return $this;
    // }

    protected function setAttributes($data = [])
    {
        $empresa = Empresa::with('contCidade', 'contBairro', 'contRua')
                            ->select('contcidade_id', 'contrua_id', 'contbairro_id', 
                                    'contnome', 'contcpf', 'contcrc', 'contcnpj', 'contcep', 'contnumero', 
                                    'contcomplemento', 'conttelefone', 'contfax', 'contemail')
                            ->where('id', Session::get('empresa_padrao')->id)->get()->first();

        $this->NOME = $empresa->contnome;
        $this->CPF = Util::pregReplaceCnpjCpf($empresa->contcpf);
        $this->CRC = $empresa->contcrc;
        $this->CNPJ = Util::pregReplaceCnpjCpf($empresa->contcnpj);
        $this->CEP = Util::pregReplaceCnpjCpf($empresa->contcep);
        $this->END = $empresa->contRua->descricao;
        $this->NUM = $empresa->contnumero;
        $this->COMPL = $empresa->contcomplemento;
        $this->BAIRRO = $empresa->contBairro->descricao;
        $this->FONE = Util::pregReplaceCnpjCpf($empresa->conttelefone);
        $this->FAX = $empresa->contfax;
        $this->EMAIL = $empresa->contemail;
        $this->COD_MUN = $empresa->contCidade->cod_ibge;

        $this->setGenericError("Contabilista");
        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'NOME'      => static::getBaseVR("Nome do contabilista", 100),
            'CPF'       => static::getBaseVR("CPF do contabilista", 11, true),
            'CRC'       => static::getBaseVR("Número de inscrição do contabilista no Conselho Regional de Contabilidade", 15),
            'CNPJ'      => static::getBaseVR("CNPJ do contabilista", 14, true, "N"),
            'CEP'       => static::getBaseVR("CEP do Contabilista", 8, true, "N"),
            'END'       => static::getBaseVR("Logradouro e endereço do imóvel", 60, false, "N"),
            'NUM'       => static::getBaseVR("Número do imóvel", false, false, "N"),
            'COMPL'     => static::getBaseVR("Dados complementares do endereço", 60, false, "N"),
            'BAIRRO'    => static::getBaseVR("Bairro em que o imóvel está situado", 60, false, "N"),
            'FONE'      => static::getBaseVR("Número do telefone", 11, false, "N"),
            'FAX'       => static::getBaseVR("Número do fax", 11, false, "N"),
            'EMAIL'     => static::getBaseVR("Endereço eletronico", false, false, "N"),
            'COD_MUN'   => static::getBaseVR("Código IBGE do Municipio", 7, true, "N"),
        ];
    }
}
