<?php
namespace App\Processors\Sped\Contribuicao\Bloco0;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;
use App\Empresa;
use Session;
use DB;

class Reg0200 extends AbstractReg
{
    // Código do item
    protected $COD_ITEM;
    // Descrição do item
    protected $DESCR_ITEM;
    // Representação alfanumérico do código de barra do produto, se houver.
    protected $COD_BARRA;
    // Código anterior do item com relação à última informação apresentada.
    protected $COD_ANT_ITEM;
    // Unidade de medida utilizada na quantificação de estoques.
    protected $UNID_INV;
    // Tipo do item – Atividades Industriais, Comerciais e Serviços:
    //     00 – Mercadoria para Revenda;
    //     01 – Matéria-Prima;
    //     02 – Embalagem;
    //     03 – Produto em Processo;
    //     04 – Produto Acabado;
    //     05 – Subproduto;
    //     06 – Produto Intermediário;
    //     07 – Material de Uso e Consumo;
    //     08 – Ativo Imobilizado;
    //     09 – Serviços;
    //     10 – Outros insumos;
    //     99 – Outras
    protected $TIPO_ITEM;
    // Código da Nomenclatura Comum do Mercosul
    protected $COD_NCM;
    // Código EX, conforme a TIPI
    protected $EX_IPI;
    // Código do gênero do item, conforme a Tabela 4.2.1.
    protected $COD_GEN;
    // Código do serviço conforme lista do Anexo I da Lei Complementar Federal nº 116/03
    protected $COD_LST;
    // Alíquota de ICMS aplicável ao item nas operações internas
    protected $ALIQ_ICMS;

    protected function setAttributes($data = [])
    {
        $produtos = $data['nf']->filter(function ($nf) {
            return $nf->nfsituacao_id == 100 || ($nf->tiponf == 'recebida' && Util::hasIn($nf->nfmodelo, ['01','1B','04','55','65']));
        })->unique(function ($nf) {
            return $nf->ucom.$nf->cprod;
        });

        $tipoitem = DB::table('spedtipoitems')->select('id','codigo')->get();

        if($produtos->count() === 0)
            $this->none = true;

        foreach ($produtos as $prod) {
            $code = $tipoitem->keyBy('id')->get($prod->nfetipoitem);
            $this->COD_ITEM = $prod->cprod;
            $this->DESCR_ITEM = $prod->produto;
            $this->COD_BARRA = $prod->cean;
            $this->COD_ANT_ITEM = "";
            $this->UNID_INV = $prod->ucom;
            $this->TIPO_ITEM = isset($code->codigo) ? $code->codigo : null;
            $this->COD_NCM = $prod->ncm;
            $this->EX_IPI = $prod->nfeextipi;
            $this->COD_GEN = strlen($prod->nfecodgen) == 1 ? "0" . $prod->nfecodgen : $prod->nfecodgen;
            $this->COD_LST = $prod->nfecodlst;
            $this->ALIQ_ICMS = $prod->picms;

            $this->setGenericError("NF Produto " . $prod->cprod);
            $this->addReg($this);
        }
        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'COD_ITEM'      => static::getBaseVR("Código do item", 60),
            'DESCR_ITEM'    => static::getBaseVR("Descrição do item", false),
            'COD_BARRA'     => static::getBaseVR("Representação alfanumérico do código de barra do produto", false, false, "N"),
            'COD_ANT_ITEM'  => static::getBaseVR("Código anterior do item com relação à última informação apresentada", 60, false, "N"),
            'UNID_INV'      => static::getBaseVR("Unidade de medida utilizada na quantificação de estoques", 6, false, "N"),
            'TIPO_ITEM'     => static::getBaseVR("Tipo do item – Atividades Industriais, Comerciais e Serviços", 2, true, "O", ['00', '01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '99']),
            'COD_NCM'       => static::getBaseVR("Código da Nomenclatura Comum do Mercosul", 8, false, "N"),
            'EX_IPI'        => static::getBaseVR("Código EX, conforme a TIPI", 3, false, "N"),
            'COD_GEN'       => static::getBaseVR("Código do gênero do item", 2, true, "N"),
            'COD_LST'       => static::getBaseVR("Código do serviço", 5, false, "N"),
            'ALIQ_ICMS'     => static::getBaseVR("Alíquota de ICMS aplicável ao item nas operações internas", 6, false, "N")
        ];
    }
}
