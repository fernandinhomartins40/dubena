<?php

namespace App\Processors\Nfe\Tributacao;

use App\Processors\Nfe\Tributacao\Base\ValidationBase;

class ImpostoDB extends ValidationBase
{

    /**
     * @var bool
     */
    public $isConsumidorFinal;

    /**
     * @var mixed
     */
    public $icms;

    /**
     * @var mixed
     */
    private $destuf;

    /**
     * @var mixed
     */
    private $emituf;

    /**
     * @var mixed
     */
    private $produto;

    /**
     * @var mixed
     */
    private $nfimposto;

    /**
     * @var mixed
     */
    private $origemicms;

    /**
     * @var mixed
     */
    private $codigocst;

    /**
     * @var mixed
     */
    private $modalidadebcicms;

    /**
     * @var mixed
     */
    private $nficmsbase;

    /**
     * @var mixed
     */
    private $nficmsaliq;

    /**
     * @var mixed
     */
    private $nficmsalimono;

    /**
     * @var mixed
     */
    private $modalidadebcicmsst;

    /**
     * @var mixed
     */
    private $mva;

    /**
     * @var mixed
     */
    private $mvareduzido;

    /**
     * @var mixed
     */
    private $aliqicmsst;

    /**
     * @var mixed
     */
    private $aliqicmsdest;

    /**
     * @var mixed
     */
    private $nfaliqdiferimento;

    /**
     * @var mixed
     */
    private $taxafecop;

    /**
     * @var mixed
     */
    private $id;

    /**
     * @var mixed
     */
    private $iduf;

    /**
     * @var mixed
     */
    private $codigopis;

    /**
     * @var mixed
     */
    private $nfpisbase;

    /**
     * @var mixed
     */
    private $nfpisaliq;

    /**
     * @var mixed
     */
    private $codigocofins;

    /**
     * @var mixed
     */
    private $nfcofinsbase;

    /**
     * @var mixed
     */
    private $motdesonicms;

    /**
     * @var mixed
     */
    private $nficmsbasest;

    /**
     * @var mixed
     */
    private $nfcofinsaliq;

    /**
     * @var mixed
     */
    private $idDest;

    /**
     * @var mixed
     */
    private $codbenef;

    private $codigoibscbs;
    private $classtribibscbs;
    private $nfibscbsbase;
    private $nfibsufaliq;
    private $nfibsufaliqmono;
    private $nfibsmunaliq;
    private $nfibsmunaliqmono;
    private $nfcbsaliq;
    private $nfcbsaliqmono;

    public $ind_gmonoret;
    public $ind_gtribregular;


    /**
     * NfeImposto constructor.
     * @param $options
     * @throws \Exception
     */
    public function __construct($options)
    {
        $this->setOptions($options);
        $this->validateOptions();
        $this->init();
    }

    /**
     * @throws \Exception
     */
    private function validateOptions()
    {
        if (is_null($this->isConsumidorFinal)) {
            $this->exception("O parâmetro \"isConsumidorFinal\" é obrigatório para gerar os impostos");
        }
        if (is_null($this->emituf)) {
            $this->exception("O parâmetro \"emituf\" é obrigatório para gerar os impostos");
        }
        if (is_null($this->destuf)) {
            $this->exception("O parâmetro \"destuf\" é obrigatório para gerar os impostos");
        }
        if (is_null($this->produto)) {
            $this->exception("O parâmetro \"produto\" é obrigatório para gerar os impostos");
        }
        if (is_null($this->nfimposto)) {
            $this->exception("O parâmetro \"nfimposto\" é obrigatório para gerar os impostos");
        }
        if (is_null($this->idDest)) {
            $this->exception("O parâmetro \"idDest\" é obrigatório para gerar os impostos");
        }
    }

    /**
     * @param $options
     * @throws \Exception
     */
    private function setOptions($options)
    {
        $errors = "";
        $vars = get_object_vars($this);
        foreach ($options as $key => $opt) {
            if (array_key_exists($key, $vars))
                $this->{$key} = $opt;
            else
                $errors .= $key;
        }
        if ($errors) {
            throw new \Exception("Parâmetros esperados não informados: " . $errors);
        }
    }

    /**s
     * @param $msg
     * @throws \Exception
     */
    private function exception($msg)
    {
        throw new \Exception($msg);
    }

    /**
     * @throws \Exception
     */
    private function init()
    {
        if ((int) $this->idDest === 1)
            $this->setImpostoNormal();
        else
            $this->setImpostoInter();

        if ($this->isConsumidorFinal) { // consumidor final usa impostos PF.
            $this->codigopis = $this->nfimposto->pfnfpis->codigo;
            $this->nfpisbase = $this->nfimposto->pfnfpisbase;
            $this->nfpisaliq = $this->nfimposto->pfnfpisaliq;

            $this->codigocofins = $this->nfimposto->pfnfcofins->codigo;
            $this->nfcofinsbase = $this->nfimposto->pfnfcofinsbase;
            $this->nfcofinsaliq = $this->nfimposto->pfnfcofinsaliq;

            //IBS/CBS
            $this->codigoibscbs = @$this->nfimposto->pfcstibscbs->codigo;
            $this->classtribibscbs = @$this->nfimposto->pfclastribibscbs->codigo;
            $this->ind_gmonoret = @$this->nfimposto->nfclastribibscbs->ind_gmonoret;
            $this->ind_gtribregular = @$this->nfimposto->nfclastribibscbs->ind_gtribregular;
            $this->nfibscbsbase = $this->nfimposto->pfibscbsbase;
            $this->nfibsufaliq = $this->nfimposto->pfibsufaliq;
            $this->nfibsmunaliq = $this->nfimposto->pfibsmunaliq;
            $this->nfcbsaliq = $this->nfimposto->pfcbsaliq;
        } else {
            $this->codigopis = $this->nfimposto->nfpis->codigo;
            $this->nfpisbase = $this->nfimposto->nfpisbase;
            $this->nfpisaliq = $this->nfimposto->nfpisaliq;

            $this->codigocofins = $this->nfimposto->nfcofins->codigo;
            $this->nfcofinsbase = $this->nfimposto->nfcofinsbase;
            $this->nfcofinsaliq = $this->nfimposto->nfcofinsaliq;

            //IBS/CBS
            $this->codigoibscbs = @$this->nfimposto->nfcstibscbs->codigo;
            $this->classtribibscbs = @$this->nfimposto->nfclastribibscbs->codigo;
            $this->ind_gmonoret = @$this->nfimposto->nfclastribibscbs->ind_gmonoret;
            $this->ind_gtribregular = @$this->nfimposto->nfclastribibscbs->ind_gtribregular;
            $this->nfibscbsbase = $this->nfimposto->nfibscbsbase;
            $this->nfibsufaliq = $this->nfimposto->nfibsufaliq;
            $this->nfibsmunaliq = $this->nfimposto->nfibsmunaliq;
            $this->nfcbsaliq = $this->nfimposto->nfcbsaliq;
        }
    }

    /**
     *  pega os dados dos impostos
     *
     * @return object
     */
    private function setImpostoNormal()
    {
        // consumidor final usa impostos PF.
        if ($this->isConsumidorFinal) {
            $this->origemicms = $this->nfimposto->pforigemicms;
            $this->codigocst = $this->nfimposto->pfnficms->codigo;
            $this->modalidadebcicms = $this->nfimposto->pfmodalidadebcicms;
            $this->nficmsbase = $this->nfimposto->pfnficmsbase;
            $this->nficmsaliq = $this->nfimposto->pfnficmsaliq;
            $this->nficmsalimono = $this->nfimposto->pfnficmsalimono;
            $this->modalidadebcicmsst = $this->nfimposto->pfmodalidadebcicmsst;
            $this->mva = 0;
            $this->mvareduzido = 0;
            $this->aliqicmsst = 0;
            $this->nfaliqdiferimento = 0;
            $this->nfaliqdiferimento = 0;
            $this->taxafecop = $this->nfimposto->pftaxafecop;
            $this->motdesonicms = $this->nfimposto->pfnfmotdesonicms;
            $this->nficmsbasest = 0;
            $this->codbenef = isset($this->nfimposto->pf_beneficiario->codbenef) ? $this->nfimposto->pf_beneficiario->codbenef : null;
            //IBS/CBS
            $this->codigoibscbs = @$this->nfimposto->pfcstibscbs->codigo;
            $this->classtribibscbs = @$this->nfimposto->pfclastribibscbs->codigo;
            $this->ind_gmonoret = @$this->nfimposto->nfclastribibscbs->ind_gmonoret;
            $this->ind_gtribregular = @$this->nfimposto->nfclastribibscbs->ind_gtribregular;
            $this->nfibscbsbase = $this->nfimposto->pfibscbsbase;
            $this->nfibsufaliq = $this->nfimposto->pfibsufaliq;
            $this->nfibsufaliqmono = $this->nfimposto->pfibsufaliqmono;
            $this->nfibsmunaliq = $this->nfimposto->pfibsmunaliq;
            $this->nfibsmunaliqmono = $this->nfimposto->pfibsmunaliqmono;
            $this->nfcbsaliq = $this->nfimposto->pfcbsaliq;
            $this->nfcbsaliqmono = $this->nfimposto->pfcbsaliqmono;
        } else {
            $this->origemicms = $this->nfimposto->origemicms;
            $this->codigocst = $this->nfimposto->nficms->codigo;
            $this->modalidadebcicms = $this->nfimposto->modalidadebcicms;
            $this->nficmsbase = $this->nfimposto->nficmsbase;
            $this->nficmsaliq = $this->nfimposto->nficmsaliq;
            $this->nficmsalimono = $this->nfimposto->nficmsalimono;
            $this->modalidadebcicmsst = $this->nfimposto->modalidadebcicmsst;
            $this->mva = $this->nfimposto->mva;
            $this->mvareduzido = $this->nfimposto->mvareduzido;
            $this->aliqicmsst = $this->nfimposto->aliqicmsst;
            $this->nfaliqdiferimento = $this->nfimposto->nfaliqdiferimento;
            $this->taxafecop = $this->nfimposto->taxafecop;
            $this->motdesonicms = $this->nfimposto->nfmotdesonicms;
            $this->nficmsbasest = $this->nfimposto->nficmsbasest;
            $this->codbenef = isset($this->nfimposto->pj_beneficiario->codbenef) ? $this->nfimposto->pj_beneficiario->codbenef : null;
            //IBS/CBS
            $this->codigoibscbs = @$this->nfimposto->nfcstibscbs->codigo;
            $this->classtribibscbs = @$this->nfimposto->nfclastribibscbs->codigo;
            $this->ind_gmonoret = @$this->nfimposto->nfclastribibscbs->ind_gmonoret;
            $this->ind_gtribregular = @$this->nfimposto->nfclastribibscbs->ind_gtribregular;
            $this->nfibscbsbase = $this->nfimposto->nfibscbsbase;
            $this->nfibsufaliq = $this->nfimposto->nfibsufaliq;
            $this->nfibsufaliqmono = $this->nfimposto->nfibsufaliqmono;
            $this->nfibsmunaliq = $this->nfimposto->nfibsmunaliq;
            $this->nfibsmunaliqmono = $this->nfimposto->nfibsmunaliqmono;
            $this->nfcbsaliq = $this->nfimposto->nfcbsaliq;    
            $this->nfcbsaliqmono = $this->nfimposto->nfcbsaliqmono;        
        }
        $this->id = $this->nfimposto->id;
        return $this;
    }

    /**
     * quando o destino da operação é interestadual
     *
     * @return object
     * @throws \Exception
     */
    private function setImpostoInter()
    {
        $nfimpostoestado = $this->nfimposto->nfimpostoestado->filter(function ($imp) {
            return $imp->origem_uf == $this->emituf && $imp->destino_uf == $this->destuf;
        })->first();

        if (is_null($nfimpostoestado)) {
            $msg = "Não foi encontrado nenhum imposto saindo "
                . "do {$this->emituf} para {$this->destuf} com o grupo fiscal {$this->produto[16]} "
                . "e operação {$this->produto[1]} do produto {$this->produto[5]}.";
            throw new \Exception($msg);
        }

        // consumidor final usa impostos PF.
        if ($this->isConsumidorFinal) {
            $this->origemicms = $nfimpostoestado->pfnficmsorigem;
            $this->codigocst = $nfimpostoestado->pfnficms->codigo;
            $this->modalidadebcicms = $nfimpostoestado->pfnficmsmodalidadebc;
            $this->nficmsbase = $nfimpostoestado->pfnficmsbase;
            $this->nficmsaliq = $nfimpostoestado->pfnficmsaliq;
            $this->modalidadebcicmsst = $nfimpostoestado->pfnficmsstmodalidadebc;
            $this->mva = 0;
            $this->mvareduzido = 0;
            $this->aliqicmsst = 0;
            $this->aliqicmsdest = $nfimpostoestado->pfaliqicmsdest;
            //Não há diferimento para venda fora do estado, exceto que haja decreto entre estados específicos
            $this->nfaliqdiferimento = 0;
            $this->taxafecop = $nfimpostoestado->pftaxafecop;
            $this->motdesonicms = $nfimpostoestado->pfnfmotdesonicms;
            $this->nficmsbasest = 0;
            $this->codbenef = isset($nfimpostoestado->pf_beneficiario->codbenef) ? $nfimpostoestado->pf_beneficiario->codbenef : null;
        } else {
            $this->origemicms = $nfimpostoestado->nficmsorigem;
            $this->codigocst = $nfimpostoestado->nficms->codigo;
            $this->modalidadebcicms = $nfimpostoestado->nficmsmodalidadebc;
            $this->nficmsbase = $nfimpostoestado->nficmsbase;
            $this->nficmsaliq = $nfimpostoestado->nficmsaliq;
            $this->modalidadebcicmsst = $nfimpostoestado->nficmsstmodalidadebc;
            $this->mva = $nfimpostoestado->mva;
            $this->mvareduzido = $nfimpostoestado->mvareduzido;
            $this->aliqicmsst = $nfimpostoestado->nficmsstaliq;
            $this->aliqicmsdest = $nfimpostoestado->pfaliqicmsdest;
            //Não há diferimento para venda fora do estado, exceto que haja decreto entre estados específicos
            $this->nfaliqdiferimento = 0;
            $this->taxafecop = $nfimpostoestado->taxafecop;
            $this->motdesonicms = $nfimpostoestado->nfmotdesonicms;
            $this->nficmsbasest = $nfimpostoestado->nficmsbasest;
            $this->codbenef = isset($nfimpostoestado->pj_beneficiario->codbenef) ? $nfimpostoestado->pj_beneficiario->codbenef : null;
        }
        $this->iduf = $nfimpostoestado->id;

        return $this;
    }

    /**
     * @param $attr
     * @return mixed
     */
    public function get($attr)
    {
        return $this->{$attr};
    }
}
