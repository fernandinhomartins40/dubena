<?php

namespace App\Processors\Nfe\Tributacao\XmlTags;

use App\Processors\Nfe\Tributacao\Base\ValidationBase;

/**
 * tag de produtos ou serviços da NF
 * @author Jeferson
 */
class TagProd extends ValidationBase
{
    /**
     * valor total do item para fins de cálculo
     * @var float
     */
    public $vItemToCalc;
    
    /**
     * Descrição do produto conforme ANP
     * @var string
     */
    public $descANP;

    /**
     * Código de produto da ANP
     * @var int|string
     */
    public $cProdANP;

    /**
     * Preencher de acordo com o código EX da TIPI. Em caso
     * de serviço, não incluir a TAG
     * @var string
     */
    public $EXTIPI;

    /**
     * Quantidade Comercial 
     * @var int
     */
    public $qCom;

    /**
     * Valor Unitário de Comercialização
     * @var float
     */
    public $vUnCom;

    /**
     * valor do item ou serviço
     * @var float
     */
    public $vProdItem;

    /**
     * Código do produto ou serviço
     * @var float
     */
    public $vProd;

    /**
     * Código do produto ou serviço
     * @var int
     */
    public $cProd;

    /**
     * GTIN (Global Trade Item Number) do
     * produto, antigo código EAN ou código
     * de barras
     * @var string
     */
    public $cEAN;

    /**
     * Descrição do produto ou serviço
     * @var string
     */
    public $xProd;

    /**
     * Código NCM com 8 dígitos 
     * @var string
     */
    public $NCM;

    /**
     * Unidade Comercial 
     * @var string
     */
    public $uCom;

    /**
     * GTIN (Global Trade Item Number) da
     * unidade tributável, antigo código EAN
     * ou código de barras
     * @var string
     */
    public $cEANTrib;

    /**
     * Unidade Tributável 
     * @var float
     */
    public $uTrib;

    /**
     * Quantidade Tributável
     * @var float
     */
    public $qTrib;

    /**
     * Valor Unitário de tributação
     * @var float
     */
    public $vUnTrib;

    /**
     * Indica se valor do Item (vProd) entra
     * no valor total da NF-e (vProd)
     * @var float
     */
    public $indTot;

    /**
     * 
     * @var float
     */
    public $xPed;

    /**
     * número do item do pedido
     * @var float
     */
    public $nItemPed;

    /**
     *
     * @var float
     */
    public $nFCI;

    /**
     *
     * @var float
     */
    public $CFOP;

    /**
     * valor da alíquota do CIDE do combustível
     * @var float
     */
    public $vAliqProd;

    /**
     * valor CIDE 
     * @var float
     */
    public $vCIDE;

    /**
     * quantidade da BC do CIDE
     * @var float
     */
    public $qBCProd;

    /**
     * valor do frete do item
     * @var float
     */
    public $vFrete;

    /**
     * valor do seguro do item
     * @var float
     */
    public $vSeg;

    /**
     * valor de outras despesas do item
     * @var float
     */
    public $vOutro;

    /**
     * valor do desconto do item
     * @var float
     */
    public $vDesc;

    /**
     * @var float
     */
    public $pGNi;

    /**
     * @var float
     */
    public $pGNn;

    /**
     * @var float
     */
    public $pGLP;

    /**
     * @var float
     */
    public $vPart;

    public $CEST;
}
