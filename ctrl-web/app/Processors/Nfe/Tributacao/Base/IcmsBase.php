<?php

namespace App\Processors\Nfe\Tributacao\Base;

use \Exception;

/**
 *
 * @author Jeferson
 */
class IcmsBase extends ValidationBase
{

    /**
     * se true, quando houver erro retorna um dd() quando alguma tag obrigatória não foi preenchida
     * @var bool
     */
    private $debug = true;

    /**
     * origem da mercadoria:
     * Nacional, Estrangeira, etc
     * @var string|int
     */
    public $orig;

    /**
     * código da CST do ICMS
     * @var string|int
     */
    public $CST;

    /**
     * Código da CST para regime Simples nacional
     * @var string|int
     */
    public $CSOSN;

    /**
     * Valor da BC do ICMS
     * @var float
     */
    public $vBC;

    /**
     * Modalidade de determinação da BC do ICMS ST
     * @var string|int
     */
    public $modBCST;

    /**
     * Percentual da margem de valor Adicionado do ICMS ST
     * @var float
     */
    public $pMVAST;

    /**
     * Percentual da Redução de BC do ICMS ST
     * @var float
     */
    public $pRedBCST;

    /**
     * Valor da BC do ICMS ST
     * @var float
     */
    public $vBCST;

    /**
     * Alíquota do ICMS ST sem o FCP
     * @var float
     */
    public $pICMSST;

    /**
     * Valor do ICMS ST
     * @var float
     */
    public $vICMSST;

    /**
     * Percentual da Redução de BC do ICMS
     * @var float
     */
    public $pRedBC;

    /**
     * Alíquota do imposto:
     * <i>Alíquota do ICMS sem o FCP.</i>
     * @var float
     */
    public $pICMS;

    /**
     * valor do ICMS
     * @var float
     */
    public $vICMS;

    /**
     * Valor do ICMS desonerado
     * @var float
     */
    public $vICMSDeson;

    /**
     * Motivo da desoneração do ICMS
     * @var int|string
     */
    public $motDesICMS;

    /**
     * Valor do ICMS diferido
     * @var float
     */
    public $vICMSDif;

    /**
     * Percentual do diferimento
     * @var float
     */
    public $pDif;

    /**
     * Valor do ICMS da Operação
     * @var float
     */
    public $vICMSOp;

    /**
     * Valor da Base de Cálculo ST do icms retido
     * @var float
     */
    public $vBCSTRet;

    /**
     * Valor do ICMS ST do icms retido
     * @var float
     */
    public $vICMSSTRet;

    /**
     * Valor da modalidade da Base de Cálculo do icms
     * @var int|string
     */
    public $modBC;

    /**
     * Percentual da BC operação própria
     * @var float
     */
    public $pBCOp;

    /**
     * UF para qual é devido o ICMS ST
     * @var string
     */
    public $UFST;

    /**
     * Valor da BC do ICMS ST da UF destino

     * @var float
     */
    public $vBCSTDest;

    /**
     * Alíquota aplicável de cálculo do crédito (Simples Nacional)
     * @var float
     */
    public $pCredSN;

    /**
     * Valor crédito do ICMS que pode ser aproveitado nos termos do art. 23 da LC 123 (Simples Nacional)
     * @var float
     */
    public $vCredICMSSN;

    /**
     * Percentual relativo ao Fundo de Combate à Pobreza (FCP).
     * @var float
     */
    public $pFCP;

    /**
     * Valor do ICMS relativo ao Fundo de Combate à Pobreza (FCP).
     * @var float
     */
    public $vFCP;

    /**
     * Informar o valor da Base de Cálculo do FCP
     * @var float
     */
    public $vBCFCP;

    /**
     * Informar o valor da Base de Cálculo do FCP ST
     * @var float
     */
    public $vBCFCPST;

    /**
     * Percentual do ICMS ST relativo ao Fundo de Combate à Pobreza (FCP).
     * @var float
     */
    public $pFCPST;

    /**
     * Valor do ICMS ST relativo ao Fundo de Combate à Pobreza (FCP).
     * @var float
     */
    public $vFCPST;

    /**
     * Alíquota suportada pelo Consumidor Final
     *  Deve ser informada a alíquota do cálculo do ICMS-ST, já
     * incluso o FCP caso incida sobre a mercadoria
     * @var float
     */
    public $pST;

    /**
     * Valor da Base de Cálculo do FCP retido anteriormente por ST
     * @var float
     */
    public $vBCFCPSTRet;

    /**
     * Percentual do FCP retido anteriormente por Substituição Tributária
     * @var float
     */
    public $pFCPSTRet;

    /**
     * Valor do FCP retido por Substituição Tributária
     * @var float
     */
    public $vFCPSTRet;

    /**
     * Quantidade tributada retida anteriormente
     * @var float
     */
    public $qBCMonoRet;

    /**
     * Aliquota ad rem do imposto retido anteriormente
     * @var float
     */
    public $adRemICMSRet;

    /**
     * Valor do ICMS retido anteriormente
     * @var float
     */
    public $vICMSMonoRet;

    /**
     * Seta para null todos os campos que não são usadas no CST 00
     */
    private function unsetFor00()
    {
        $this->pRedBC = null;
        $this->vICMSDeson = null;
        $this->motDesICMS = null;
        $this->modBCST = null;
        $this->pMVAST = null;
        $this->pRedBCST = null;
        $this->vBCST = null;
        $this->pICMSST = null;
        $this->vICMSST = null;
        $this->pDif = null;
        $this->vICMSDif = null;
        $this->vICMSOp = null;
        $this->vBCSTRet = null;
        $this->vICMSSTRet = null;
        $this->pCredSN = null;
        $this->vCredICMSSN = null;
        $this->pBCOp = null;
        $this->UFST = null;
        $this->vBCSTDest = null;
        $this->vBCFCP = null;
        $this->vBCFCPST = null;
        $this->pFCPST = null;
        $this->vFCPST = null;
        $this->pST = null;
        $this->vBCFCPSTRet = null;
        $this->pFCPSTRet = null;
        $this->vFCPSTRet = null;
        $this->qBCMonoRet = null;
        $this->adRemICMSRet = null;
        $this->vICMSMonoRet = null;
    }

    /**
     * Seta para null todos os campos que não são usadas no CST 10
     */
    private function unsetFor10()
    {
        $this->pRedBC = null;
        $this->vICMSDeson = null;
        $this->motDesICMS = null;
        $this->pDif = null;
        $this->vICMSDif = null;
        $this->vICMSOp = null;
        $this->vBCSTRet = null;
        $this->vICMSSTRet = null;
        $this->pCredSN = null;
        $this->vCredICMSSN = null;
        $this->pBCOp = null;
        $this->UFST = null;
        $this->vBCSTDest = null;
        $this->pST = null;
        $this->vBCFCPSTRet = null;
        $this->pFCPSTRet = null;
        $this->vFCPSTRet = null;
        $this->qBCMonoRet = null;
        $this->adRemICMSRet = null;
        $this->vICMSMonoRet = null;
    }

    /**
     * Seta para null todos os campos que não são usadas no CST 20
     */
    private function unsetFor20()
    {
        $this->modBCST = null;
        $this->pMVAST = null;
        $this->pRedBCST = null;
        $this->vBCST = null;
        $this->pICMSST = null;
        $this->vICMSST = null;
        $this->pDif = null;
        $this->vICMSDif = null;
        $this->vICMSOp = null;
        $this->vBCSTRet = null;
        $this->vICMSSTRet = null;
        $this->pCredSN = null;
        $this->vCredICMSSN = null;
        $this->pBCOp = null;
        $this->UFST = null;
        $this->vBCSTDest = null;
        $this->vBCFCPST = null;
        $this->pFCPST = null;
        $this->vFCPST = null;
        $this->pST = null;
        $this->vBCFCPSTRet = null;
        $this->pFCPSTRet = null;
        $this->vFCPSTRet = null;
        $this->qBCMonoRet = null;
        $this->adRemICMSRet = null;
        $this->vICMSMonoRet = null;
    }

    /**
     * Seta para null todos os campos que não são usadas no CST 30
     */
    private function unsetFor30()
    {
        $this->modBC = null;
        $this->pRedBC = null;
        $this->vBC = null;
        $this->pICMS = null;
        $this->vICMS = null;
        $this->pDif = null;
        $this->vICMSDif = null;
        $this->vICMSOp = null;
        $this->vBCSTRet = null;
        $this->vICMSSTRet = null;
        $this->pCredSN = null;
        $this->vCredICMSSN = null;
        $this->UFST = null;
        $this->pBCOp = null;
        $this->vBCSTDest = null;
        $this->pFCP = null;
        $this->vFCP = null;
        $this->vBCFCP = null;
        $this->pST = null;
        $this->vBCFCPSTRet = null;
        $this->pFCPSTRet = null;
        $this->vFCPSTRet = null;
        $this->qBCMonoRet = null;
        $this->adRemICMSRet = null;
        $this->vICMSMonoRet = null;
    }

    /**
     * Seta para null todos os campos que não são usadas no CST 40
     */
    private function unsetFor40()
    {
        $this->vBC = null;
        $this->modBCST = null;
        $this->pMVAST = null;
        $this->pRedBCST = null;
        $this->vBCST = null;
        $this->pICMSST = null;
        $this->vICMSST = null;
        $this->pRedBC = null;
        $this->pICMS = null;
        $this->vICMS = null;
        $this->vICMSDif = null;
        $this->pDif = null;
        $this->vICMSOp = null;
        $this->vBCSTRet = null;
        $this->vICMSSTRet = null;
        $this->modBC = null;
        $this->pBCOp = null;
        $this->UFST = null;
        $this->vBCSTDest = null;
        $this->pCredSN = null;
        $this->vCredICMSSN = null;
        $this->pFCP = null;
        $this->vFCP = null;
        $this->vBCFCP = null;
        $this->vBCFCPST = null;
        $this->pFCPST = null;
        $this->vFCPST = null;
        $this->pST = null;
        $this->vBCFCPSTRet = null;
        $this->pFCPSTRet = null;
        $this->vFCPSTRet = null;
        $this->qBCMonoRet = null;
        $this->adRemICMSRet = null;
        $this->vICMSMonoRet = null;
    }

    /**
     * Seta para null todos os campos que não são usadas no CST 51
     */
    private function unsetFor51()
    {
        $this->modBCST = null;
        $this->pMVAST = null;
        $this->pRedBCST = null;
        $this->vBCST = null;
        $this->pICMSST = null;
        $this->vICMSST = null;
        $this->vICMSDeson = null;
        $this->motDesICMS = null;
        $this->vBCSTRet = null;
        $this->vICMSSTRet = null;
        $this->pBCOp = null;
        $this->UFST = null;
        $this->vBCSTDest = null;
        $this->pCredSN = null;
        $this->vCredICMSSN = null;
        $this->vBCFCPST = null;
        $this->pFCPST = null;
        $this->vFCPST = null;
        $this->pST = null;
        $this->vBCFCPSTRet = null;
        $this->pFCPSTRet = null;
        $this->vFCPSTRet = null;
        $this->qBCMonoRet = null;
        $this->adRemICMSRet = null;
        $this->vICMSMonoRet = null;
    }

    /**
     * Seta para null todos os campos que não são usadas no CST 60
     */
    private function unsetFor60()
    {
        $this->modBC = null;
        $this->pRedBC = null;
        $this->vBC = null;
        $this->pICMS = null;
        $this->vICMS = null;
        $this->vICMSDeson = null;
        $this->motDesICMS = null;
        $this->modBCST = null;
        $this->pMVAST = null;
        $this->pRedBCST = null;
        $this->vBCST = null;
        $this->pICMSST = null;
        $this->vICMSST = null;
        $this->pDif = null;
        $this->vICMSDif = null;
        $this->vICMSOp = null;
        $this->pCredSN = null;
        $this->vCredICMSSN = null;
        $this->pBCOp = null;
        $this->UFST = null;
        $this->vBCSTDest = null;
        $this->pFCP = null;
        $this->vFCP = null;
        $this->vBCFCP = null;
        $this->vBCFCPST = null;
        $this->pFCPST = null;
        $this->vFCPST = null;
        $this->qBCMonoRet = null;
        $this->adRemICMSRet = null;
        $this->vICMSMonoRet = null;
    }

    /**
     * Seta para null todos os campos que não são usadas no CST 60
     */
    private function unsetFor61()
    {
        $this->modBC = null;
        $this->pRedBC = null;
        $this->vBC = null;
        $this->pICMS = null;
        $this->vICMS = null;
        $this->vICMSDeson = null;
        $this->motDesICMS = null;
        $this->modBCST = null;
        $this->pMVAST = null;
        $this->pRedBCST = null;
        $this->vBCST = null;
        $this->pICMSST = null;
        $this->vICMSST = null;
        $this->pDif = null;
        $this->vICMSDif = null;
        $this->vICMSOp = null;
        $this->pCredSN = null;
        $this->vCredICMSSN = null;
        $this->pBCOp = null;
        $this->UFST = null;
        $this->vBCSTDest = null;
        $this->pFCP = null;
        $this->vFCP = null;
        $this->vBCFCP = null;
        $this->vBCFCPST = null;
        $this->pFCPST = null;
        $this->vFCPST = null;
    }

    /**
     * Seta para null todos os campos que não são usadas no CST 70
     */
    private function unsetFor70()
    {
        $this->pDif = null;
        $this->vICMSDif = null;
        $this->vICMSOp = null;
        $this->vBCSTRet = null;
        $this->vICMSSTRet = null;
        $this->pCredSN = null;
        $this->vCredICMSSN = null;
        $this->pBCOp = null;
        $this->UFST = null;
        $this->vBCSTDest = null;
        $this->pST = null;
        $this->vBCFCPSTRet = null;
        $this->pFCPSTRet = null;
        $this->vFCPSTRet = null;
        $this->qBCMonoRet = null;
        $this->adRemICMSRet = null;
        $this->vICMSMonoRet = null;
    }

    /**
     * Seta para null todos os campos que não são usadas no CST 90
     */
    private function unsetFor90()
    {
        $this->vICMSDif = null;
        $this->pDif = null;
        $this->vICMSOp = null;
        $this->vBCSTRet = null;
        $this->vICMSSTRet = null;
        $this->pBCOp = null;
        $this->UFST = null;
        $this->vBCSTDest = null;
        $this->pCredSN = null;
        $this->vCredICMSSN = null;
        $this->pST = null;
        $this->vBCFCPSTRet = null;
        $this->pFCPSTRet = null;
        $this->vFCPSTRet = null;
        $this->qBCMonoRet = null;
        $this->adRemICMSRet = null;
        $this->vICMSMonoRet = null;
    }

    /**
     * Seta para null todos os campos que não são usadas no CST 101
     */
    private function unsetFor101()
    {
        $this->pRedBC = null;
        $this->pICMS = null;
        $this->vICMS = null;
        $this->vICMSDeson = null;
        $this->motDesICMS = null;
        $this->modBCST = null;
        $this->pMVAST = null;
        $this->pRedBCST = null;
        $this->vBCST = null;
        $this->pICMSST = null;
        $this->vICMSST = null;
        $this->pDif = null;
        $this->vICMSDif = null;
        $this->vICMSOp = null;
        $this->vBCSTRet = null;
        $this->vICMSSTRet = null;
        $this->pFCP = null;
        $this->vFCP = null;
        $this->vBCFCP = null;
        $this->vBCFCPST = null;
        $this->pFCPST = null;
        $this->vFCPST = null;
        $this->pST = null;
        $this->vBCFCPSTRet = null;
        $this->pFCPSTRet = null;
        $this->vFCPSTRet = null;
        $this->qBCMonoRet = null;
        $this->adRemICMSRet = null;
        $this->vICMSMonoRet = null;
    }

    /**
     * Seta para null todos os campos que não são usadas no CST 102
     */
    private function unsetFor102()
    {
        $this->modBC = null;
        $this->pRedBC = null;
        $this->vBC = null;
        $this->pICMS = null;
        $this->vICMS = null;
        $this->vICMSDeson = null;
        $this->motDesICMS = null;
        $this->modBCST = null;
        $this->pMVAST = null;
        $this->pRedBCST = null;
        $this->vBCST = null;
        $this->pICMSST = null;
        $this->vICMSST = null;
        $this->pDif = null;
        $this->vICMSDif = null;
        $this->vICMSOp = null;
        $this->vBCSTRet = null;
        $this->vICMSSTRet = null;
        $this->pCredSN = null;
        $this->vCredICMSSN = null;
        $this->pFCP = null;
        $this->vFCP = null;
        $this->vBCFCP = null;
        $this->vBCFCPST = null;
        $this->pFCPST = null;
        $this->vFCPST = null;
        $this->pST = null;
        $this->vBCFCPSTRet = null;
        $this->pFCPSTRet = null;
        $this->vFCPSTRet = null;
        $this->qBCMonoRet = null;
        $this->adRemICMSRet = null;
        $this->vICMSMonoRet = null;
    }

    /**
     * Seta para null todos os campos que não são usadas no CST 201
     */
    private function unsetFor201()
    {
        $this->modBC = null;
        $this->pRedBC = null;
        $this->vBC = null;
        $this->pICMS = null;
        $this->vICMS = null;
        $this->vICMSDeson = null;
        $this->motDesICMS = null;
        $this->pDif = null;
        $this->vICMSDif = null;
        $this->vICMSOp = null;
        $this->vBCSTRet = null;
        $this->vICMSSTRet = null;
        $this->pFCP = null;
        $this->vFCP = null;
        $this->vBCFCP = null;
        $this->pST = null;
        $this->vBCFCPSTRet = null;
        $this->pFCPSTRet = null;
        $this->vFCPSTRet = null;
        $this->qBCMonoRet = null;
        $this->adRemICMSRet = null;
        $this->vICMSMonoRet = null;
    }

    /**
     * Seta para null todos os campos que não são usadas no CST 202
     */
    private function unsetFor202()
    {
        $this->modBC = null;
        $this->pRedBC = null;
        $this->vBC = null;
        $this->pICMS = null;
        $this->vICMS = null;
        $this->vICMSDeson = null;
        $this->motDesICMS = null;
        $this->pDif = null;
        $this->vICMSDif = null;
        $this->vICMSOp = null;
        $this->vBCSTRet = null;
        $this->vICMSSTRet = null;
        $this->pCredSN = null;
        $this->vCredICMSSN = null;
        $this->pFCP = null;
        $this->vFCP = null;
        $this->vBCFCP = null;
        $this->pST = null;
        $this->vBCFCPSTRet = null;
        $this->pFCPSTRet = null;
        $this->vFCPSTRet = null;
        $this->qBCMonoRet = null;
        $this->adRemICMSRet = null;
        $this->vICMSMonoRet = null;
    }

    /**
     * Seta para null todos os campos que não são usadas no CST 500
     */
    private function unsetFor500()
    {
        $this->modBC = null;
        $this->pRedBC = null;
        $this->vBC = null;
        $this->pICMS = null;
        $this->vICMS = null;
        $this->vICMSDeson = null;
        $this->motDesICMS = null;
        $this->modBCST = null;
        $this->pMVAST = null;
        $this->pRedBCST = null;
        $this->vBCST = null;
        $this->pICMSST = null;
        $this->vICMSST = null;
        $this->pDif = null;
        $this->vICMSDif = null;
        $this->vICMSOp = null;
        $this->pCredSN = null;
        $this->vCredICMSSN = null;
        $this->pFCP = null;
        $this->vFCP = null;
        $this->vBCFCP = null;
        $this->vBCFCPST = null;
        $this->pFCPST = null;
        $this->vFCPST = null;
    }

    /**
     * Seta para null todos os campos que não são usadas no CST 900
     */
    private function unsetFor900()
    {
        $this->vICMSDeson = null;
        $this->motDesICMS = null;
        $this->pDif = null;
        $this->vICMSDif = null;
        $this->vICMSOp = null;
        $this->vBCSTRet = null;
        $this->vICMSSTRet = null;
        $this->pFCP = null;
        $this->vFCP = null;
        $this->vBCFCP = null;
        $this->pST = null;
        $this->vBCFCPSTRet = null;
        $this->pFCPSTRet = null;
        $this->vFCPSTRet = null;
        $this->qBCMonoRet = null;
        $this->adRemICMSRet = null;
        $this->vICMSMonoRet = null;
    }

    /**
     * Seta para null todos os campos que não são usadas
     * caso a cst seja diferente das informadas nos outros métodos
     */
    private function unsetForAnothers()
    {
        $this->modBC = null;
        $this->pRedBC = null;
        $this->vBC = null;
        $this->pICMS = null;
        $this->vICMS = null;
        $this->vICMSDeson = null;
        $this->motDesICMS = null;
        $this->modBCST = null;
        $this->pMVAST = null;
        $this->pRedBCST = null;
        $this->vBCST = null;
        $this->pICMSST = null;
        $this->vICMSST = null;
        $this->pDif = null;
        $this->vICMSDif = null;
        $this->vICMSOp = null;
        $this->vBCSTRet = null;
        $this->vICMSSTRet = null;
        $this->pCredSN = null;
        $this->vCredICMSSN = null;
    }

    //tag não desenvolvida por conta de um levantamente que fizemos com a Ju, onde chegamos a conclusão de que
    //a tag icmsPart é usada somente para vendas relacionadas a veículos
//    private function unsetForPart()
//    {
//        $this->vICMSDeson = null;
//        $this->motDesICMS = null;
//        $this->pDif = null;
//        $this->vICMSDif = null;
//        $this->vICMSOp = null;
//        $this->vBCSTRet = null;
//        $this->vICMSSTRet = null;
//        $this->pCredSN = null;
//        $this->vCredICMSSN = null;
//        $this->vBCSTDest = null;
//    }

    /**
     * Limpa valores do icms dependendo do código da CST
     * @return IcmsBase
     * @throws Exception
     */
    public function clearUnusedTags()
    {
        $cst = strval($this->CST);

        if ($cst === '00') {
            $this->unsetFor00();
        } elseif ($cst === '10') {
            $this->unsetFor10();
        } elseif ($cst === '20') {
            $this->unsetFor20();
        } elseif ($cst === '30') {
            $this->unsetFor30();
        } elseif (hasStrIn($cst, ['40', '41', '50'])) {
            $this->unsetFor40();
        } elseif ($cst === '51') {
            $this->unsetFor51();
        } elseif ($cst === '60') {
            $this->unsetFor60();
        } elseif ($cst === '61') {
            $this->unsetFor61();
        } elseif ($cst === '70') {
            $this->unsetFor70();
        } elseif ($cst === '90') {
            $this->unsetFor90();
        } elseif ($cst === '101') {
            $this->unsetFor101();
        } elseif (hasStrIn($cst, ['102', '103', '300', '400'])) {
            $this->unsetFor102();
        } elseif ($cst === '201') {
            $this->unsetFor201();
        } elseif (hasStrIn($cst, ['202', '203'])) {
            $this->unsetFor202();
        } elseif ($cst === '500') {
            $this->unsetFor500();
        } elseif ($cst === '900') {
            $this->unsetFor900();
        } else {
            $this->unsetForAnothers();
        }

        if ($this->pRedBC <= 0 && in_array($cst, ["51", "90", "900"])) {
            $this->pRedBC = null;
        }

        if (in_array($cst, ["10", "30", "70", "90", "201", "202", "203", "900"])) {
            if ($this->pRedBCST <= 0) {
                $this->pRedBCST = null;
            }

            if ($this->pMVAST <= 0) {
                $this->pMVAST = null;
            }
        }

        return $this->validate();
    }

    /**
     * seta para '' as tags que estão nulas
     * função usada ao instanciar a classe, após armazenar alguns valores básicos das csts
     */
    public function toEmptyTags()
    {
        $vars = get_object_vars($this);
        foreach ($vars as $key => $value) {
            if (is_null($value))
                $this->{$key} = '';
        }
    }

    /**
     * valida os campos das csts que são obrigarótias
     * @return $this
     * @throws Exception
     */
    public function validate()
    {
        $vars = get_object_vars($this);
        $errors = "";

        foreach ($vars as $key => $value) {
            $isAllowedFields = !hasStrIn($key, ["allIcms", "debug", "icms"]);
            $hasError = (!is_null($value) && empty($value) && !$value <= 0) && $isAllowedFields;
            if ($hasError) {
                $errors .= "Campo $key é obrigatório para o cst $this->CST. \n";
            }

            if (!is_null($value) && !empty($value) && $this->isFloatKey($key)) {
                $decimals = strlen(substr(strrchr($value, "."), 1));
                if ($decimals <= 1)
                    $decimals = 2;
                $this->{$key} = sprintf('%0.' . $decimals . 'f', $value);
            }
        }

        if ($errors !== "") {
            throw new \Exception($errors);
        }

        return $this;
    }

    /**
     * @param $key
     * @return bool
     */
    public function isFloatKey($key)
    {
        $notFloat = [
            'debug',
            'orig',
            'CST',
            'CSOSN',
            'modBCST',
            'motDesICMS',
            'modBC',
            'UFST'
        ];
        return !in_array(strval($key), $notFloat);
    }
}
