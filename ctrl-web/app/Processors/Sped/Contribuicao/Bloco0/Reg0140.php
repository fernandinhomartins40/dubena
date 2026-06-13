<?php
namespace App\Processors\Sped\Contribuicao\Bloco0;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;
use App\Empresa;
use Session;

class Reg0140 extends AbstractReg
{
    // Código de identificação do estabelecimento
    protected $COD_EST;
    // Nome empresarial do estabelecimento
    protected $NOME;
    // Número de inscrição do estabelecimento no CNPJ
    protected $CNPJ;
    // Sigla da unidade da federação do estabelecimento
    protected $UF;
    // Inscrição Estadual do estabelecimento, se contribuinte de ICMS
    protected $IE;
    // Código do município do domicílio fiscal do estabelecimento,
    // conforme a tabela IBGE
    protected $COD_MUN;
    // Inscrição Municipal do estabelecimento, se contribuinte do ISS
    protected $IM;
    // Inscrição do estabelecimento na Suframa
    protected $SUFRAMA;

    protected function setAttributes($data=[])
    {
        $e = Empresa::with('cidade', 'rua', 'bairro')
                            ->select('cidade_id', 'razao_social', 'cnpj', 'uf', 'inscricao_estadual', 'bairro_id', 'rua_id')
                            ->where('id', Session::get('empresa_padrao')->id)->get()->first();
        $this->COD_EST = $e->id;
        $this->NOME = $e->razao_social;
        $this->CNPJ = Util::pregReplaceCnpjCpf($e->cnpj);
        $this->UF = $e->uf;
        $this->IE = $e->inscricao_estadual;
        $this->COD_MUN = $e->cidade->cod_ibge;
        $this->IM = $e->inscricao_municipal;
        $this->SUFRAMA = $e->suframa;
        $this->setGenericError("Empresa");
        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'COD_EST'   => static::getBaseVR("Código de identificação do estabelecimento", 60, false, "N"),
            'NOME'      => static::getBaseVR("Nome empresarial do estabelecimento", 100),
            'CNPJ'      => static::getBaseVR("Número de inscrição do estabelecimento no CNPJ", 14, true),
            'UF'        => static::getBaseVR("Sigla da unidade da federação do estabelecimento", 2, true),
            'IE'        => static::getBaseVR("Inscrição Estadual do estabelecimento, se contribuinte de ICMS", 14, false, "N"),
            'COD_MUN'   => static::getBaseVR("Código IBGE do município do domicílio fiscal do estabelecimento", 7, true),
            'IM'        => static::getBaseVR("Inscrição Municipal do estabelecimento, se contribuinte do ISS", false, false, "N"),
            'SUFRAMA'   => static::getBaseVR("Inscrição do estabelecimento na Suframa", 9, true, "N"),
        ];

    }

}
