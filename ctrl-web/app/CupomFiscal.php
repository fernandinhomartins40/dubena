<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\CupomFiscal
 *
 * @property string|null $assinaturaqrcode
 * @property string|null $cdv
 * @property int $cliente_id
 * @property string|null $cnf
 * @property string|null $created_at
 * @property string|null $cuf
 * @property string|null $demi
 * @property string|null $destcnpj
 * @property string|null $destcpf
 * @property string $destnro
 * @property string $destuf
 * @property string $destxbairro
 * @property string|null $destxcpl
 * @property string $destxlgr
 * @property string $destxmun
 * @property string $destxnome
 * @property string $emitcnpj
 * @property string|null $emitcregtrib
 * @property string|null $emitcregtribissqn
 * @property string $emitie
 * @property string|null $emitim
 * @property string $emitindratissqn
 * @property string|null $emitxfant
 * @property string|null $emitxnome
 * @property int $empresa_id
 * @property int $grupo_id
 * @property string|null $hemi
 * @property string|null $icmsvcfe
 * @property string|null $icmsvcofins
 * @property string|null $icmsvcofinsst
 * @property string|null $icmsvdesc
 * @property string|null $icmsvicms
 * @property string|null $icmsvoutro
 * @property string|null $icmsvpis
 * @property string|null $icmsvpisst
 * @property string|null $icmsvprod
 * @property int $id
 * @property string|null $infcpl
 * @property string|null $issqnvbc
 * @property string|null $issqnvcofins
 * @property string|null $issqnvcofinsst
 * @property string|null $issqnviss
 * @property string|null $issqnvpis
 * @property string|null $issqnvpisst
 * @property string|null $mod
 * @property string|null $ncfe
 * @property string|null $nseriesat
 * @property string $numerocaixa
 * @property string $qti_cnpj
 * @property string $signac
 * @property string|null $tpamb
 * @property string|null $updated_at
 * @property int $user_id
 * @property string|null $vacressubtot
 * @property string|null $vcfelei12741
 * @property string|null $vdescsubtot
 * @property-read \App\Cliente $cliente
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereASSINATURAQRCODE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereCDV($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereCLIENTEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereCNF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereCUF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereDEMI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereDESTCNPJ($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereDESTCPF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereDESTNRO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereDESTUF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereDESTXBAIRRO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereDESTXCPL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereDESTXLGR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereDESTXMUN($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereDESTXNOME($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereEMITCNPJ($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereEMITCREGTRIB($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereEMITCREGTRIBISSQN($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereEMITIE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereEMITIM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereEMITINDRATISSQN($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereEMITXFANT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereEMITXNOME($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereHEMI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereICMSVCFE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereICMSVCOFINS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereICMSVCOFINSST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereICMSVDESC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereICMSVICMS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereICMSVOUTRO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereICMSVPIS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereICMSVPISST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereICMSVPROD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereINFCPL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereISSQNVBC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereISSQNVCOFINS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereISSQNVCOFINSST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereISSQNVISS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereISSQNVPIS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereISSQNVPISST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereMOD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereNCFE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereNSERIESAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereNUMEROCAIXA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereQTICNPJ($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereSIGNAC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereTPAMB($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereUSERID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereVACRESSUBTOT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereVCFELEI12741($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereVDESCSUBTOT($value)
 * @mixin \Eloquent
 * @property string|null $ASSINATURAQRCODE
 * @property string|null $CDV
 * @property int|null $CENTROCUSTO_ID
 * @property int $CLIENTE_ID
 * @property string|null $CNF
 * @property int|null $CONDICAOPAGAMENTO_ID
 * @property string|null $CREATED_AT
 * @property int|null $CUF
 * @property string|null $DEMI
 * @property string|null $DESTCNPJ
 * @property string|null $DESTCPF
 * @property string $DESTNRO
 * @property string $DESTUF
 * @property string $DESTXBAIRRO
 * @property string|null $DESTXCPL
 * @property string $DESTXLGR
 * @property string $DESTXMUN
 * @property string $DESTXNOME
 * @property string $EMITCNPJ
 * @property string|null $EMITCREGTRIB
 * @property string|null $EMITCREGTRIBISSQN
 * @property string $EMITIE
 * @property string|null $EMITIM
 * @property string $EMITINDRATISSQN
 * @property string|null $EMITXFANT
 * @property string|null $EMITXNOME
 * @property int $EMPRESA_ID
 * @property int|null $FINANCEIRO_ID
 * @property int $GRUPO_ID
 * @property string|null $HEMI
 * @property float|null $ICMSVCFE
 * @property float|null $ICMSVCOFINS
 * @property float|null $ICMSVCOFINSST
 * @property float|null $ICMSVDESC
 * @property float|null $ICMSVICMS
 * @property float|null $ICMSVOUTRO
 * @property float|null $ICMSVPIS
 * @property float|null $ICMSVPISST
 * @property float|null $ICMSVPROD
 * @property int $ID
 * @property string|null $INFCPL
 * @property float|null $ISSQNVBC
 * @property float|null $ISSQNVCOFINS
 * @property float|null $ISSQNVCOFINSST
 * @property float|null $ISSQNVISS
 * @property float|null $ISSQNVPIS
 * @property float|null $ISSQNVPISST
 * @property string|null $MOD
 * @property string|null $NCFE
 * @property int $NFOPERACAO_ID
 * @property string|null $NSERIESAT
 * @property string $NUMEROCAIXA
 * @property int|null $PLANOCONTA_ID
 * @property string|null $PRODUTOSJSON
 * @property string|null $PROTOCOLO
 * @property string|null $PROTOCOLORETORNOCANCELAMENTO
 * @property string $QTI_CNPJ
 * @property string $SIGNAC
 * @property int $STATUS
 * @property string $STATUS_DESCRICAO
 * @property string|null $TPAMB
 * @property string|null $UPDATED_AT
 * @property int $USER_ID
 * @property float|null $VACRESSUBTOT
 * @property float|null $VCFELEI12741
 * @property float|null $VDESCSUBTOT
 * @property string|null $XML
 * @property string|null $XMLRETORNO
 * @property string|null $XMLRETORNOCANCELAMENTO
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereCENTROCUSTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereCONDICAOPAGAMENTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereFINANCEIROID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereNFOPERACAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal wherePLANOCONTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal wherePRODUTOSJSON($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal wherePROTOCOLO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal wherePROTOCOLORETORNOCANCELAMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereSTATUS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereSTATUSDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereXML($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereXMLRETORNO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscal whereXMLRETORNOCANCELAMENTO($value)
 * @property-read \App\Condicaopagamento $condicaoPagamento
 * @property-read \App\Nfoperacao $operacao
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\CupomFiscalParcela[] $parcelas
 */
class CupomFiscal extends Model
{
    protected $table = "cuponsfiscais";

    protected $fillable = [
        "qti_cnpj", "signac",  "numerocaixa", "assinaturaqrcode", "tpamb", "cdv", "hemi", "demi", "ncfe",
        "nseriesat", "mod", "cnf", "cuf", "emitcnpj", "emitie", "emitim", "emitindratissqn", "emitcregtribissqn",
        "emitxnome", "emitxfant", "emitcregtrib", "destcnpj", "destxnome", "destcpf", "destxlgr", "destnro",
        "destxcpl", "destxbairro", "destxmun", "destuf", "icmsvicms", "icmsvprod", "icmsvdesc", "icmsvpis",
        "icmsvcofins", "icmsvpisst", "icmsvcofinsst", "icmsvoutro", "icmsvcfe", "issqnvbc", "issqnviss",
        "issqnvpis", "issqnvcofins", "issqnvpisst", "issqnvcofinsst", "vdescsubtot", "vacressubtot", "vcfelei12741",
        "infcpl", "grupo_id", "empresa_id", "cliente_id", "user_id", "status", "status_descricao", "xml", "xmlretorno",
        "protocolo", "protocoloretornocancelamento", "xmlretornocancelamento", "nfoperacao_id", "planoconta_id",
        "financeiro_id", "centrocusto_id", "condicaopagamento_id", "produtosjson"
    ];

    /**
     * @return BelongsTo
     */
    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo', 'grupo_id');
    }

    /**\
     * @return BelongsTo
     */
    public function empresa()
    {
        return $this->belongsTo('App\Empresa', 'empresa_id');
    }

    /**
     * @return BelongsTo
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /**
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\User');
    }

    /**
     * @return BelongsTo
     */
    public function operacao()
    {
        return $this->belongsTo('App\Nfoperacao', 'nfoperacao_id');
    }

    /**
     * @return BelongsTo
     */
    public function condicaoPagamento()
    {
        return $this->belongsTo('App\CondicaoPagamento', 'condicaopagamento_id');
    }

    /**
     * @return HasMany
     */
    public function parcelas()
    {
        return $this->hasMany('App\CupomFiscalParcela', "cupomfiscal_id");
    }
}
