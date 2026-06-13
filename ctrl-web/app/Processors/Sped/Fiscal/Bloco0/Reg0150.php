<?php

namespace App\Processors\Sped\Fiscal\Bloco0;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;
use App\Helpers\Utils\NfUtil;

class Reg0150 extends AbstractReg
{

    /**
     * Código de identificação do participante no arquivo
     * @var mixed
     */
    protected $COD_PART;

    /**
     * Nome pessoal ou empresarial do participante
     * @var mixed
     */
    protected $NOME;

    /**
     * Código do país do participante, conforme a tabela indicada no item 3.2.1.
     * @var mixed
     */
    protected $COD_PAIS;

    /**
     * CNPJ do participante.
     * @var mixed
     */
    protected $CNPJ;

    /**
     * CPF do participante
     * @var mixed
     */
    protected $CPF;

    /**
     * Inscrição Estadual do participante
     * @var mixed
     */
    protected $IE;

    /**
     * Código do município, conforme a tabela IBGE
     * @var mixed
     */
    protected $COD_MUN;

    /**
     * Número de inscrição do participante na Suframa
     * @var mixed
     */
    protected $SUFRAMA;

    /**
     * Logradouro e endereço do imóvel
     * @var mixed
     */
    protected $END;

    /**
     * Número do imóvel
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
     * Modelo da NF
     * @var mixed
     */
    protected $nfmodelo;

    /**
     * retorna o array das validações dos campos
     */
    protected function getValidationArray()
    {
        return [
            'COD_PART' => static::getBaseVR("Código de identificação do participante no arquivo", 60),
            'NOME'     => static::getBaseVR("Nome pessoal ou empresarial do participante", 100),
            'COD_PAIS' => static::getBaseVR("Código do país do participante", 5),
            'CNPJ'     => static::getBaseVR("CNPJ do participante", 14, true, "OC"),
            'CPF'      => static::getBaseVR("CPF do participante", 11, true, "OC"),
            'IE'       => static::getBaseVR("Inscrição Estadual do participante", 14, false, "OC"),
            'COD_MUN'  => static::getBaseVR("Cód do município, conforme a tabela IBGE", 7, true, "OC"),
            'SUFRAMA'  => static::getBaseVR("Nº do participante na SUFRAMA", 9, true, "OC"),
            'END'      => static::getBaseVR("Logradouro e endereço do imóvel", 60),
            'NUM'      => static::getBaseVR("Número do imóvel", 10, false, "OC"),
            'COMPL'    => static::getBaseVR("Dados complementares do endereço", 60, false, "OC"),
            'BAIRRO'   => static::getBaseVR("Bairro em que o imóvel está situado", 60, false, "OC")
        ];
    }

    /**
     * seta os atributos da classe
     * @param array $data
     * @return $this
     */
    protected function setAttributes($data = [])
    {
        $notas = $data['nf']->filter(function ($nf) {
                    $models = [
                        '21', '22', '01', 'A1', '1B', '04', '55', '07', '08',
                        '8B', '09', '10', '11', '26', '27', '57', '67', '63',
                        '06', '28', '29'
                    ];
                    $allowedE = $nf->tiponf == 'emitida' && NfUtil::isAuthorized($nf->nfsituacao_id) && $nf->nfmodelo == '55';
                    $allowedR = $nf->tiponf == 'recebida' && Util::hasIn($nf->nfmodelo, $models);
                    return ($allowedE || $allowedR);
                })->unique('cliente_id');

        if (!$notas->count()) {
            $this->none = true;
        }

        foreach ($notas as $nf) {
            $this->COD_PART = $nf->cliente_id;
            $this->NOME = Util::replaceAccent($nf->destrazaosocial);
            $this->COD_PAIS = $nf->destpaiscodigoibge;
            $this->CNPJ = Util::replaceSpecialChars($nf->destcnpj, true, true);
            $this->CPF = Util::replaceSpecialChars($nf->destcpf, true, true);
            $this->IE = Util::replaceSpecialChars($nf->destie, true, true);
            $this->COD_MUN = $nf->destcidadecodigoibge;
            $this->SUFRAMA = Util::replaceSpecialChars($nf->suframa, true, true);
            $this->END = Util::replaceAccent($nf->destendereco);
            $this->NUM = Util::replaceAccent($nf->destnumero);
            $this->COMPL = Util::replaceAccent($nf->destcomplemento);
            $this->BAIRRO = Util::replaceAccent($nf->destbairro);
            $this->nfmodelo = $nf->nfmodelo;

            $this->setGenericError("NF Cliente " . $nf->cliente_id);
            $this->addReg($this);
        }

        return $this;
    }

}
