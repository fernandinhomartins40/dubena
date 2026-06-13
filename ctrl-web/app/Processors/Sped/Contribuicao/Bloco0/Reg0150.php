<?php
namespace App\Processors\Sped\Contribuicao\Bloco0;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;
use App\Helpers\Utils\NfUtil;
use Session;

class Reg0150 extends AbstractReg
{
    // Código de identificação do participante no arquivo
    protected $COD_PART;
    // Nome pessoal ou empresarial do participante
    protected $NOME;
    // Código do país do participante, conforme a tabela indicada no item 3.2.1.
    protected $COD_PAIS;
    // CNPJ do participante.
    protected $CNPJ;
    // CPF do participante
    protected $CPF;
    // Inscrição Estadual do participante
    protected $IE;
    // Código do município, conforme a tabela IBGE
    protected $COD_MUN;
    // Número de inscrição do participante na Suframa
    protected $SUFRAMA;
    // Logradouro e endereço do imóvel
    protected $END;
    // Número do imóvel
    protected $NUM;
    // Dados complementares do endereço
    protected $COMPL;
    // Bairro em que o imóvel está situado
    protected $BAIRRO;

    protected $nfmodelo;

    protected function setAttributes($data=[])
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
        
        if($notas->count() === 0)
            $this->none = true;
        
        foreach ($notas as $nf) {
            $this->COD_PART = $nf->cliente_id;
            $this->NOME = $nf->destrazaosocial;
            $this->COD_PAIS = $nf->destpaiscodigoibge;
            $this->CNPJ = Util::pregReplaceCnpjCpf($nf->destcnpj);
            $this->CPF = Util::pregReplaceCnpjCpf($nf->destcpf);
            $this->IE = $nf->destie;
            $this->COD_MUN = $nf->destcidadecodigoibge;
            $this->SUFRAMA = $nf->suframa;
            $this->END = $nf->destendereco;
            $this->NUM = $nf->destnumero;
            $this->COMPL = $nf->destcomplemento;
            $this->BAIRRO = $nf->destbairro;
            $this->nfmodelo = $nf->nfmodelo;

            $this->setGenericError("NF Cliente " . $nf->cliente_id);
            $this->addReg($this);
        }
        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'COD_PART'  => static::getBaseVR("Código de identificação do participante no arquivo", 60),
            'NOME'      => static::getBaseVR("Nome pessoal ou empresarial do participante", 100),
            'COD_PAIS'  => static::getBaseVR("Código do país do participante", 5),
            'CNPJ'      => static::getBaseVR("CNPJ do participante", 14, true, "N"),
            'CPF'       => static::getBaseVR("CPF do participante", 11, true, "N", $this->calls('CPF')),
            'IE'        => static::getBaseVR("Inscrição Estadual do participante", 14, false, "N"),
            'COD_MUN'   => static::getBaseVR("Código do município IBGE", 7, true, "N"),
            'SUFRAMA'   => static::getBaseVR("Número de inscrição do participante na Suframa", 9, true, "N"),
            'END'       => static::getBaseVR("Logradouro e endereço do imóvel", 60, false, "N"),
            'NUM'       => static::getBaseVR("Número do imóvel", false, false, "N"),
            'COMPL'     => static::getBaseVR("Dados complementares do endereço", 60, false, "N"),
            'BAIRRO'    => static::getBaseVR("Bairro em que o imóvel está situado", 60, false, "N"),
        ];
    }

    protected function calls($index)
    {
        $col = collect([
            'CPF'   => function ($value) {
                if (!$this->CNPJ && !$value) {
                    $this->addError("Obrigatoriamente um dos campos, CPF ou CNPJ, deverá ser preenchido");
                }
            }
        ]);

        return $col->get($index);
    }
}
