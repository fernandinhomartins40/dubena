<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Nfimpostoestado
 *
 * @property string|null $CREATED_AT
 * @property string|null $DESTINO_UF
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property float|null $MVA
 * @property float|null $MVAREDUZIDO
 * @property float|null $NFALIQDIFERIMENTO
 * @property int $NFICMS_ID
 * @property float $NFICMSALIQ
 * @property float|null $NFICMSBASE
 * @property float|null $NFICMSBASEST
 * @property int|null $NFICMSMODALIDADEBC
 * @property int $NFICMSORIGEM
 * @property float $NFICMSSTALIQ
 * @property int|null $NFICMSSTMODALIDADEBC
 * @property int $NFIMPOSTO_ID
 * @property int|null $NFMOTDESONICMS
 * @property string|null $ORIGEM_UF
 * @property float|null $PFALIQICMSDEST
 * @property float|null $PFMVA
 * @property int $PFNFICMS_ID
 * @property float $PFNFICMSALIQ
 * @property float|null $PFNFICMSBASE
 * @property int|null $PFNFICMSMODALIDADEBC
 * @property int $PFNFICMSORIGEM
 * @property float $PFNFICMSSTALIQ
 * @property int|null $PFNFICMSSTMODALIDADEBC
 * @property int|null $PFNFMOTDESONICMS
 * @property float $PFTAXAFECOP
 * @property float|null $TAXAFECOP
 * @property string|null $UPDATED_AT
 * @property-read \App\Estado $destinoUf
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Nficms $nficms
 * @property-read \App\Nfimposto $nfimposto
 * @property-read \App\Estado $origemUf
 * @property-read \App\Nficms $pfnficms
 * @property-read \Illuminate\Database\Eloquent\Collection|\Venturecraft\Revisionable\Revision[] $revisionHistory
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado whereDESTINOUF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado whereMVA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado whereMVAREDUZIDO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado whereNFALIQDIFERIMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado whereNFICMSALIQ($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado whereNFICMSBASE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado whereNFICMSBASEST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado whereNFICMSID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado whereNFICMSMODALIDADEBC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado whereNFICMSORIGEM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado whereNFICMSSTALIQ($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado whereNFICMSSTMODALIDADEBC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado whereNFIMPOSTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado whereNFMOTDESONICMS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado whereORIGEMUF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado wherePFALIQICMSDEST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado wherePFMVA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado wherePFNFICMSALIQ($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado wherePFNFICMSBASE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado wherePFNFICMSID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado wherePFNFICMSMODALIDADEBC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado wherePFNFICMSORIGEM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado wherePFNFICMSSTALIQ($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado wherePFNFICMSSTMODALIDADEBC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado wherePFNFMOTDESONICMS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado wherePFTAXAFECOP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado whereTAXAFECOP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado whereUPDATEDAT($value)
 * @mixin \Eloquent
 * @property int|null $BENEFICIARIO_ID
 * @property int|null $PFBENEFICIARIO_ID
 * @property-read \App\Beneficiario $pf_beneficiario
 * @property-read \App\Beneficiario $pj_beneficiario
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado whereBENEFICIARIOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfimpostoestado wherePFBENEFICIARIOID($value)
 */
class Nfimpostoestado extends Model
{

    use \App\Services\RevisionsTraitService;

    protected $identity = "empresa_id";

    protected $fillable = ['grupo_id', 'empresa_id', 'nfimposto_id', 'origem_uf', 'destino_uf',
        'nficms_id', 'nficmsaliq', 'nficmsbase', 'nficmsmodalidadebc', 'nficmsstmodalidadebc',
        'nficmsorigem', 'nficmsstaliq', 'mva', 'pfnficms_id', 'pfnficmsaliq',
        'pfnficmsmodalidadebc', 'pfnficmsstmodalidadebc', 'pfnficmsorigem',
        'pfnficmsstaliq', 'pfmva', 'pftaxafecop', 'mvareduzido', 'nfaliqdiferimento',
        'nfmotdesonicms', 'pfnfmotdesonicms', 'pfnficmsbase', 'nficmsbasest', 'pfaliqicmsdest', 'taxafecop',
        'beneficiario_id', 'pfbeneficiario_id'
    ];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function nfimposto()
    {
        return $this->belongsTo('App\Nfimposto', 'nfimposto_id');
    }

    public function origemUf()
    {
        return $this->belongsTo('App\Estado', 'origem_uf');
    }

    public function destinoUf()
    {
        return $this->belongsTo('App\Estado', 'destino_uf');
    }

    public function nficms()
    {
        return $this->belongsTo('App\Nficms', 'nficms_id');
    }

    public function pfnficms()
    {
        return $this->belongsTo('App\Nficms', 'pfnficms_id');
    }

    public function pf_beneficiario()
    {
        return $this->belongsTo('App\Beneficiario', 'pfbeneficiario_id', 'id');
    }

    public function pj_beneficiario()
    {
        return $this->belongsTo('App\Beneficiario', 'beneficiario_id', 'id');
    }
}
