<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Nfimposto
 *
 * @property float $ALIQICMSST
 * @property string|null $CREATED_AT
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $INFORMACOESADICIONAL
 * @property int|null $MODALIDADEBCICMS
 * @property int|null $MODALIDADEBCICMSST
 * @property float|null $MVA
 * @property float|null $MVAREDUZIDO
 * @property float|null $NFALIQDIFERIMENTO
 * @property int $NFCOFINS_ID
 * @property float $NFCOFINSALIQ
 * @property float|null $NFCOFINSALIQCRED
 * @property float $NFCOFINSBASE
 * @property int $NFGRUPOFISCAL_ID
 * @property int $NFICMS_ID
 * @property float $NFICMSALIQ
 * @property float|null $NFICMSBASE
 * @property float|null $NFICMSBASEST
 * @property int|null $NFMOTDESONICMS
 * @property int $NFOPERACAO_ID
 * @property int $NFPIS_ID
 * @property float $NFPISALIQ
 * @property float|null $NFPISALIQCRED
 * @property float $NFPISBASE
 * @property int $ORIGEMICMS
 * @property string|null $PFINFORMACOESADICIONAL
 * @property int|null $PFMODALIDADEBCICMS
 * @property int|null $PFMODALIDADEBCICMSST
 * @property float|null $PFMVA
 * @property int $PFNFCOFINS_ID
 * @property float $PFNFCOFINSALIQ
 * @property float $PFNFCOFINSBASE
 * @property int $PFNFICMS_ID
 * @property float $PFNFICMSALIQ
 * @property float|null $PFNFICMSBASE
 * @property int|null $PFNFMOTDESONICMS
 * @property int $PFNFPIS_ID
 * @property float $PFNFPISALIQ
 * @property float|null $PFNFPISALIQCRED
 * @property float $PFNFPISBASE
 * @property int $PFORIGEMICMS
 * @property float|null $PFTAXAFECOP
 * @property string $PISCOFINSGERACREDITO
 * @property int|null $PISCOFINSNATRECEITA
 * @property int|null $PISCOFINSTIPOBCCREDITO
 * @property int|null $PISCOFINSTIPOCREDITO
 * @property float|null $TAXAFECOP
 * @property string|null $UPDATED_AT
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Nfcofins $nfcofins
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfemitidaitem[] $nfemitidaitem
 * @property-read \App\Nfgrupofiscal $nfgrupofiscal
 * @property-read \App\Nficms $nficms
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfimpostoestado[] $nfimpostoestado
 * @property-read \App\Nfoperacao $nfoperacao
 * @property-read \App\Nfpis $nfpis
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfrecebidaitem[] $nfrecebidaitem
 * @property-read \App\Nfcofins $pfnfcofins
 * @property-read \App\Nficms $pfnficms
 * @property-read \App\Nfpis $pfnfpis
 * @property-read \Illuminate\Database\Eloquent\Collection|\Venturecraft\Revisionable\Revision[] $revisionHistory
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereALIQICMSST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereINFORMACOESADICIONAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereMODALIDADEBCICMS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereMODALIDADEBCICMSST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereMVA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereMVAREDUZIDO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereNFALIQDIFERIMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereNFCOFINSALIQ($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereNFCOFINSALIQCRED($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereNFCOFINSBASE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereNFCOFINSID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereNFGRUPOFISCALID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereNFICMSALIQ($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereNFICMSBASE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereNFICMSBASEST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereNFICMSID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereNFMOTDESONICMS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereNFOPERACAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereNFPISALIQ($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereNFPISALIQCRED($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereNFPISBASE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereNFPISID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereORIGEMICMS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto wherePFINFORMACOESADICIONAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto wherePFMODALIDADEBCICMS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto wherePFMODALIDADEBCICMSST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto wherePFMVA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto wherePFNFCOFINSALIQ($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto wherePFNFCOFINSBASE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto wherePFNFCOFINSID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto wherePFNFICMSALIQ($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto wherePFNFICMSBASE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto wherePFNFICMSID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto wherePFNFMOTDESONICMS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto wherePFNFPISALIQ($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto wherePFNFPISALIQCRED($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto wherePFNFPISBASE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto wherePFNFPISID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto wherePFORIGEMICMS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto wherePFTAXAFECOP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto wherePISCOFINSGERACREDITO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto wherePISCOFINSNATRECEITA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto wherePISCOFINSTIPOBCCREDITO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto wherePISCOFINSTIPOCREDITO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereTAXAFECOP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereUPDATEDAT($value)
 * @mixin \Eloquent
 * @property int|null $BENEFICIARIO_ID
 * @property int|null $PFBENEFICIARIO_ID
 * @property-read \App\Beneficiario $pf_beneficiario
 * @property-read \App\Beneficiario $pj_beneficiario
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto whereBENEFICIARIOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimposto wherePFBENEFICIARIOID($value)
 */
class Nfimposto extends Model
{

    use \App\Services\RevisionsTraitService;

    protected $identity = "empresa_id";

    protected $fillable = [
        'grupo_id', 'empresa_id', 'nfoperacao_id', 'nfgrupofiscal_id',
        'nfcofins_id', 'nfcofinsaliq', 'nfcofinsaliqcred', 'nfcofinsbase', 'pfnfcofins_id',
        'pfnfcofinsaliq', 'pfnfcofinsbase', 'nficms_id', 'nficmsaliq', 'nficmsbase', 'pfnficms_id',
        'pfnficmsaliq', 'pfnficmsbase', 'nfpis_id', 'nfpisaliq', 'nfpisaliqcred', 'nfpisbase',
        'pfnfpis_id', 'pfnfpisaliq', 'pfnfpisaliqcred', 'pfnfpisbase', 'modalidadebcicms',
        'modalidadebcicmsst', 'origemicms', 'informacoesadicionalfisco', 'informacoesadicional',
        'pfmodalidadebcicms', 'pfmodalidadebcicmsst', 'pforigemicms', 'pfinformacoesadicionalfisco',
        'pfinformacoesadicional', 'piscofinsgeracredito', 'piscofinstipocredito', 'piscofinsnatreceita',
        'piscofinstipobccredito', 'mva', 'pfmva', 'mvareduzido', 'aliqicmsst', 'pfaliqicmsst',
        'pftaxafecop', 'nfaliqdiferimento', 'nfmotdesonicms', 'pfnfmotdesonicms', 'nficmsbasest',
        'taxafecop', 'beneficiario_id', 'pfbeneficiario_id', 'nficmsalimono', 'pfnficmsalimono',
        // IBS/CBS
        'nfcstibscbs_id', 'nfclastribibscbs_id', 
        'pfcstibscbs_id', 'pfclastribibscbs_id', 
        'nfibscbsbase', 'pfibscbsbase', 
        'nfibsufaliq', 'nfibsufaliqmono', 'pfibsufaliq', 'pfibsufaliqmono', 
        'nfibsmunaliq', 'nfibsmunaliqmono', 'pfibsmunaliq', 'pfibsmunaliqmono', 
        'nfcbsaliq',  'nfcbsaliqmono', 'pfcbsaliq',  'pfcbsaliqmono'
    ];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function nfoperacao()
    {
        return $this->belongsTo('App\Nfoperacao');
    }

    public function nfgrupofiscal()
    {
        return $this->belongsTo('App\Nfgrupofiscal');
    }

    public function nfcofins()
    {
        return $this->belongsTo('App\Nfcofins', 'nfcofins_id');
    }

    public function pfnfcofins()
    {
        return $this->belongsTo('App\Nfcofins', 'pfnfcofins_id');
    }

    public function nficms()
    {
        return $this->belongsTo('App\Nficms', 'nficms_id');
    }

    public function pfnficms()
    {
        return $this->belongsTo('App\Nficms', 'pfnficms_id');
    }

    public function nfpis()
    {
        return $this->belongsTo('App\Nfpis', 'nfpis_id');
    }

    public function pfnfpis()
    {
        return $this->belongsTo('App\Nfpis', 'pfnfpis_id');
    }

    function nfrecebidaitem()
    {
        return $this->hasMany('App\Nfrecebidaitem');
    }

    function nfimpostoestado()
    {
        return $this->hasMany('App\Nfimpostoestado');
    }

    function nfemitidaitem()
    {
        return $this->hasMany('App\Nfemitidaitem');
    }

    public function pf_beneficiario()
    {
        return $this->belongsTo('App\Beneficiario', 'pfbeneficiario_id', 'id');
    }

    public function pj_beneficiario()
    {
        return $this->belongsTo('App\Beneficiario', 'beneficiario_id', 'id');
    }

    public function nfcstibscbs()
    {
        return $this->belongsTo('App\Nfcst', 'nfcstibscbs_id');
    }

    public function pfcstibscbs()
    {
        return $this->belongsTo('App\Nfcst', 'pfcstibscbs_id');
    }

    public function nfclastribibscbs()
    {
        return $this->belongsTo('App\Nfclastrib', 'nfclastribibscbs_id');
    }

    public function pfclastribibscbs()
    {
        return $this->belongsTo('App\Nfclastrib', 'pfclastribibscbs_id');
    }
}
