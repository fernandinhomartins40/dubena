<?php

namespace App;

use Dompdf\Exception;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Produto
 *
 * @property string|null $ATIVO
 * @property string|null $CREATED_AT
 * @property float|null $CUSTOFRETE
 * @property float|null $CUSTOMEDIO
 * @property string $DESCRICAO
 * @property string|null $EAN
 * @property string|null $EANTRIB
 * @property int $EMPRESA_ID
 * @property string|null $ESPECIE
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $MARCA
 * @property string|null $NCM
 * @property string|null $NFCEST
 * @property float|null $NFEALIQIPI
 * @property float|null $NFEBCIPI
 * @property int|null $NFECODENQUADRAMENTOIPI
 * @property string|null $NFECODGEN
 * @property string|null $NFECODLST
 * @property string|null $NFECPRODANP
 * @property string|null $NFEDESCANP
 * @property string|null $NFEDESCRICAOFISCAL
 * @property string|null $NFEEXTIPI
 * @property string $NFEPERMITE
 * @property float|null $NFEQBCPROD
 * @property int|null $NFETIPOITEM
 * @property float|null $NFEVALIQPROD
 * @property float|null $NFEVCIDE
 * @property int|null $NFGRUPOFISCAL_ID
 * @property int|null $NFIPI_ID
 * @property string|null $OBSERVACAO
 * @property float|null $PESOBRUTO
 * @property float|null $PESOLIQUIDO
 * @property float $PGLP
 * @property float $PGNI
 * @property float $PGNN
 * @property float|null $PRECOVENDA
 * @property float|null $PRECOVENDAMINIMO
 * @property int $PRODUTOCLASSE_ID
 * @property int|null $PRODUTORETORNAVEL_ID
 * @property int|null $RESSARCIMENTOPRODUTO_ID
 * @property int|null $TIPO_GLP
 * @property int $UNIDADEMEDIDA_ID
 * @property string|null $UPDATED_AT
 * @property string $VASILHAMERETORNAVEL
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Atualizacaoprecos[] $atualizacaoPreco
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Clienteproduto[] $clienteProduto
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Colaboradorcomissao[] $colaboradorComissao
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Comodatoitem[] $comodatoitem
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Estoquefechamentosetor[] $estoqueFechamentoSetor
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Estoquefisicosetor[] $estoqueFisicoSetor
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Estoqueproduto[] $estoqueProduto
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Estoquerequisicaoitem[] $estoqueRequisicaoItem
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Estoquesetor[] $estoqueSetor
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Estoquesetorhistorico[] $estoqueSetorHistorico
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Estoquetransferenciaitem[] $estoqueTransferenciaItem
 * @property-read \App\Nfgrupofiscal $grupoFiscal
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Inventarioitems[] $inventarioitems
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Metavenda[] $metaVenda
 * @property-read \App\Nfcest $nfCest
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfemitidavolume[] $nfEmitidaVolume
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfrecebidavolume[] $nfRecebidaVolume
 * @property-read \App\Nfipi $nfipi
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Pedidoitem[] $pedidoItem
 * @property-read \App\Produtoclasse $produtoclasse
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Clienteprodutosconvenio[] $produtoconvenio
 * @property-read \App\Produto $produtoretornavel
 * @property-read \Illuminate\Database\Eloquent\Collection|\Venturecraft\Revisionable\Revision[] $revisionHistory
 * @property-read \App\Unidademedida $unidademedida
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Valegas[] $valeGas
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Valegasvenda[] $valeGasVenda
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereCUSTOFRETE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereCUSTOMEDIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereEAN($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereEANTRIB($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereESPECIE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereMARCA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereNCM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereNFCEST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereNFEALIQIPI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereNFEBCIPI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereNFECODENQUADRAMENTOIPI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereNFECODGEN($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereNFECODLST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereNFECPRODANP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereNFEDESCANP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereNFEDESCRICAOFISCAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereNFEEXTIPI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereNFEPERMITE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereNFEQBCPROD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereNFETIPOITEM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereNFEVALIQPROD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereNFEVCIDE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereNFGRUPOFISCALID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereNFIPIID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereOBSERVACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto wherePESOBRUTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto wherePESOLIQUIDO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto wherePGLP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto wherePGNI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto wherePGNN($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto wherePRECOVENDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto wherePRECOVENDAMINIMO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto wherePRODUTOCLASSEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto wherePRODUTORETORNAVELID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereRESSARCIMENTOPRODUTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereTIPOGLP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereUNIDADEMEDIDAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereVASILHAMERETORNAVEL($value)
 * @mixin \Eloquent
 * @property string|null $NFENATREC
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereNFENATREC($value)
 */
class Produto extends Model
{

    use \App\Services\RevisionsTraitService;

    protected $identity = "empresa_id";

    protected $fillable = [
        "grupo_id", "empresa_id", "produtoclasse_id", "unidademedida_id", "produtoretornavel_id",
        "vasilhameretornavel", "descricao", "customedio", "custofrete", "precovenda", "precovendaminimo", "pesoliquido", "pesobruto",
        "observacao", "ativo", "ean", "ncm", "especie", "marca", "nfepermite", "nfedescricaofiscal", "nfetipoitem", "nfeextipi",
        "nfecodgen", "nfecodlst", "nfenatrec", "nfecodenquadramentoipi", "nfecprodanp", "nfeqbcprod", "nfevaliqprod", "nfevcide",
        "nfgrupofiscal_id", "nfipi_id", "nfealiqipi", "nfebcipi", "nfcest", "tipo_glp", "ressarcimentoproduto_id", "nfedescanp",
        "pglp", "pgnn", "pgni", "eantrib", "enviaappnf", "diasgiro", "precogasdopovo"
    ];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function grupoFiscal()
    {
        return $this->belongsTo(Nfgrupofiscal::class, 'nfgrupofiscal_id');
    }

    public function produtoclasse()
    {
        return $this->belongsTo('App\Produtoclasse');
    }

    public function unidademedida()
    {
        return $this->belongsTo('App\Unidademedida');
    }

    public function produtoretornavel()
    {
        return $this->hasOne('App\Produto', 'id', 'produtoretornavel_id');
    }

    public function clienteProduto()
    {
        return $this->hasMany('App\Clienteproduto');
    }

    public function colaboradorComissao()
    {
        return $this->hasMany('App\Colaboradorcomissao');
    }

    public function comodatoitem()
    {
        return $this->hasMany('App\Comodatoitem');
    }

    public function estoqueFechamentoSetor()
    {
        return $this->hasMany('App\Estoquefechamentosetor');
    }

    public function estoqueFisicoSetor()
    {
        return $this->hasMany('App\Estoquefisicosetor');
    }

    public function estoqueProduto()
    {
        return $this->hasMany('App\Estoqueproduto');
    }

    public function estoqueRequisicaoItem()
    {
        return $this->hasMany('App\Estoquerequisicaoitem');
    }

    public function estoqueSetor()
    {
        return $this->hasMany('App\Estoquesetor');
    }

    public function estoqueSetorHistorico()
    {
        return $this->hasMany('App\Estoquesetorhistorico');
    }

    public function estoqueTransferenciaItem()
    {
        return $this->hasMany('App\Estoquetransferenciaitem');
    }

    public function metaVenda()
    {
        return $this->hasMany('App\Metavenda');
    }

    public function valeGasVenda()
    {
        return $this->hasMany('App\Valegasvenda');
    }

    public function valeGas()
    {
        return $this->hasMany('App\Valegas');
    }

    public function pedidoItem()
    {
        return $this->hasMany('App\Pedidoitem');
    }

    function nfRecebidaVolume()
    {
        return $this->hasMany('App\Nfrecebidavolume');
    }

    function nfEmitidaVolume()
    {
        return $this->hasMany('App\Nfemitidavolume');
    }

    function nfCest()
    {
        return $this->belongsTo('App\NFcest');
    }

    public function nfipi()
    {
        return $this->belongsTo('App\Nfipi', 'nfipi_id');
    }

    public function selectProduto($empresa)
    {
        return $this->where(['empresa_id' => $empresa, 'ativo' => 1])->pluck('descricao', 'id');
    }

    public function inventarioitems()
    {
        return $this->hasMany('App\Inventarioitems');
    }

    public function atualizacaoPreco()
    {
        return $this->hasMany('App\Atualizacaoprecos');
    }

    public static function updateCustoMedio($produto, $qtdetotalestoque, $produtoqtde, $produtovlrvenda, $estorno = true)
    {
        $customediototal = $produto->customedio * $qtdetotalestoque;
        $valorvendatotal = $produtoqtde * $produtovlrvenda;
        $qdeestoque = $produto->customedio == 0 ? 0 : $qtdetotalestoque;

        if ($estorno)
            $qdeaposestorno = $qdeestoque - $produtoqtde;
        else
            $qdeaposestorno = $qdeestoque + $produtoqtde;

        if ($qdeaposestorno <= 0) {
            $novocustomedio = 0;
        } else {
            if ($estorno)
                $novocustomedio = ($customediototal - $valorvendatotal) / $qdeaposestorno;
            else
                $novocustomedio = ($customediototal + $valorvendatotal) / $qdeaposestorno;
        }

        $produto->update(["customedio" => $novocustomedio]);
    }

    public static function updatePGLP($produtosJson, $empresa_id)
    {
        try {
            $produtos = array_column(json_decode($produtosJson), 4);
            $produtosImp = implode(",", $produtos);
            $subSelDate = "SELECT MAX(DATAHORAEMISSAO) FROM NFRECEBIDAS nf";
            $subSelDate .= " INNER JOIN NFRECEBIDAITEMS it ON it.NFRECEBIDA_ID = nf.ID";
            $subSelDate .= "    AND it.CPROD IN (" . $produtosImp . ")";
            $subSelDate .= " WHERE nf.EMPRESA_ID = " . $empresa_id;

            $subSelJoin = "SELECT pGLP             AS nf_pGLP,";
            $subSelJoin .= "    pGNi               AS nf_pGNi,";
            $subSelJoin .= "    pGNn               AS nf_pGNn,";
            $subSelJoin .= "    nf.DATAHORAEMISSAO AS nf_data,";
            $subSelJoin .= "    i.cProd            AS nf_cprod";
            $subSelJoin .= "  FROM NFRECEBIDAS nf";
            $subSelJoin .= "  INNER JOIN NFRECEBIDAITEMS i";
            $subSelJoin .= "  ON i.NFRECEBIDA_ID       = nf.ID";
            $subSelJoin .= "  WHERE nf.DATAHORAEMISSAO = (" . $subSelDate . ") AND nf.EMPRESA_ID  = " . $empresa_id . "";
            $subSelJoin .= " limit 1";

            $strSel = " SELECT COALESCE(docs.nf_pGNi, prod.PGNI) AS pgni,";
            $strSel .= "  COALESCE(docs.nf_pGNn, prod.PGNN)      AS pgnn,";
            $strSel .= "  COALESCE(docs.nf_pGLP, prod.PGLP)      AS pglp,";
            $strSel .= "  PROD.ID                           AS PRODUTO_ID";
            $strSel .= " FROM produtos prod";
            $strSel .= " LEFT JOIN";
            $strSel .= "  (" . $subSelJoin . ") docs ON docs.nf_cprod = prod.id";
            $strSel .= " WHERE prod.id             IN (" . $produtosImp . ")";

            $percents = \DB::select($strSel);

            foreach ($percents as $p) {
                \DB::statement("UPDATE PRODUTOS SET pgni = " . $p->pgni . ", pgnn = " . $p->pgnn
                    . ", pglp = " . $p->pglp . " WHERE id = " . $p->produto_id);
            }
        } catch (Exception $e) {
            \Session::flash('message_info', $e->getMessage());
            return false;
        }
        return true;
    }

    public function produtoconvenio()
    {
        return $this->hasMany('App\Clienteprodutosconvenio');
    }

    public function origens()
    {
        return $this->hasMany('App\Produtoorigem');
    }
}
