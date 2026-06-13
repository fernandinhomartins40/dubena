<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Nfemitidaitem
 *
 * @property string|null $CEAN
 * @property string|null $CEANTRIB
 * @property string|null $CEST
 * @property int $CFOP
 * @property string $CODIGOLOTE
 * @property string $CPROD
 * @property string|null $CPRODANP
 * @property string|null $CREATED_AT
 * @property int $CST
 * @property int|null $CSTCOFINS
 * @property int|null $CSTIPI
 * @property int $CSTPIS
 * @property float $CUSTOMEDIO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int $INDTOT
 * @property int|null $MODBC
 * @property int|null $MODBCST
 * @property int|null $MOTDESICMS
 * @property int|null $MOVIMENTAESTOQUE
 * @property string $NCM
 * @property int $NFEMITIDA_ID
 * @property int $NFIMPOSTO_ID
 * @property int $NFOPERACAO_ID
 * @property int|null $NITEMPED
 * @property int $ORIG
 * @property float|null $PBCOP
 * @property float $PCOFINS
 * @property float|null $PCREDSN
 * @property float|null $PDIF
 * @property float|null $PESOB
 * @property float|null $PESOL
 * @property float|null $PFCP
 * @property float|null $PFCPST
 * @property float|null $PFCPSTRET
 * @property float|null $PICMS
 * @property float|null $PICMSST
 * @property float|null $PIPI
 * @property float|null $PMVAST
 * @property float $PPIS
 * @property float|null $PREDBC
 * @property float|null $PREDBCST
 * @property float|null $PST
 * @property float|null $QBCPROD
 * @property float $QCOM
 * @property float $QESTOQUE
 * @property float $QTRIB
 * @property int|null $QVOL
 * @property int $SETOR_ID
 * @property string $TAGCOFINS
 * @property string $TAGICMS
 * @property string|null $TAGIPI
 * @property string $TAGPIS
 * @property string $UCOM
 * @property string|null $UPDATED_AT
 * @property string $UTRIB
 * @property float|null $VALIQPROD
 * @property float|null $VBC
 * @property float|null $VBCCOFINS
 * @property float|null $VBCFCP
 * @property float|null $VBCFCPST
 * @property float|null $VBCFCPSTRET
 * @property float|null $VBCIPI
 * @property float|null $VBCPIS
 * @property float|null $VBCST
 * @property float|null $VBCSTDEST
 * @property float|null $VBCSTRET
 * @property float|null $VCIDE
 * @property float $VCOFINS
 * @property float|null $VCREDICMSSN
 * @property float|null $VDESC
 * @property float|null $VFCP
 * @property float|null $VFCPST
 * @property float|null $VFCPSTRET
 * @property float|null $VFRETE
 * @property float|null $VICMS
 * @property float|null $VICMSDESON
 * @property float|null $VICMSDIF
 * @property float|null $VICMSOP
 * @property float|null $VICMSST
 * @property float|null $VICMSSTRET
 * @property float|null $VIPI
 * @property float|null $VOUTRO
 * @property float $VPIS
 * @property float $VPROD
 * @property float|null $VSEG
 * @property float $VUNCOM
 * @property float $VUNTRIB
 * @property string $XPROD
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Nfemitida $nfEmitida
 * @property-read \App\Nfimposto $nfImposto
 * @property-read \App\Nfoperacao $nfoperacao
 * @property-read \App\Produto $produto
 * @property-read \App\Setor $setor
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereCEAN($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereCEANTRIB($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereCEST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereCFOP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereCODIGOLOTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereCPROD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereCPRODANP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereCST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereCSTCOFINS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereCSTIPI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereCSTPIS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereCUSTOMEDIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereINDTOT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereMODBC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereMODBCST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereMOTDESICMS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereMOVIMENTAESTOQUE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereNCM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereNFEMITIDAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereNFIMPOSTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereNFOPERACAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereNITEMPED($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereORIG($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem wherePBCOP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem wherePCOFINS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem wherePCREDSN($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem wherePDIF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem wherePESOB($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem wherePESOL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem wherePFCP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem wherePFCPST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem wherePFCPSTRET($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem wherePICMS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem wherePICMSST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem wherePIPI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem wherePMVAST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem wherePPIS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem wherePREDBC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem wherePREDBCST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem wherePST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereQBCPROD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereQCOM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereQESTOQUE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereQTRIB($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereQVOL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereSETORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereTAGCOFINS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereTAGICMS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereTAGIPI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereTAGPIS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereUCOM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereUTRIB($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVALIQPROD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVBC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVBCCOFINS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVBCFCP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVBCFCPST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVBCFCPSTRET($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVBCIPI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVBCPIS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVBCST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVBCSTDEST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVBCSTRET($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVCIDE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVCOFINS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVCREDICMSSN($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVDESC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVFCP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVFCPST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVFCPSTRET($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVFRETE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVICMS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVICMSDESON($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVICMSDIF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVICMSOP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVICMSST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVICMSSTRET($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVIPI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVOUTRO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVPIS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVPROD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVSEG($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVUNCOM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVUNTRIB($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereXPROD($value)
 * @mixin \Eloquent
 * @property float $PGLP
 * @property float $PGNI
 * @property float $PGNN
 * @property float $VPART
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem wherePGLP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem wherePGNI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem wherePGNN($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaitem whereVPART($value)
 */
class Nfemitidaitem extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'nfemitida_id', 'nfimposto_id',
        'nfoperacao_id', 'setor_id', 'cprod', 'cean', 'xprod', 'ncm', 'cfop', 'ucom',
        'qcom', 'vuncom', 'vprod', 'ceantrib', 'utrib', 'qtrib', 'vuntrib', 'indTot',
        'tagicms', 'orig', 'cst', 'modbc', 'vbc', 'picms', 'vicms', 'vbcstret', 'vicmsstret',
        'tagpis', 'cstpis', 'vbcpis', 'ppis', 'vpis', 'tagcofins', 'cstcofins', 'vbccofins',
        'pcofins', 'vcofins', 'qestoque', 'tagipi', 'cstipi', 'vbcipi', 'pipi', 'vipi',
        'vdesc', 'vfrete', 'codigolote', 'picmsst', 'cprodanp', 'qbcprod', 'valiqprod', 'vcide',
        'qvol', 'pesol', 'pesob', 'customedio', 'modbcst', 'pmvast', 'predbcst', 'vbcst',
        'vicmsst', 'predbc', 'vicmsdeson', 'motdesicms', 'vicmsdif', 'pdif', 'vicmsop', 'pbcop',
        'vbcstdest', 'pcredsn', 'vcredicmssn', 'pfcp', 'vfcp', 'vbcfcp', 'vbcfcpst', 'pfcpst',
        'vfcpst', 'pst', 'vbcfcpstret', 'pfcpstret', 'vfcpstret', 'vseg', 'voutro', 'cest', 'nitemped',
        'movimentaestoque', 'pGLP', 'pGNn', 'pGNi', 'vPart',
        'cstibscbs', 'clastribibscbs', 'vbcibscbs', 'predbcibscbs', 'pibsuf', 'vibsuf', 'pibsmun', 'vibsmun', 'pcbs', 'vcbs' ];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    function nfEmitida()
    {
        return $this->belongsTo('App\Nfemitida');
    }

    function nfImposto()
    {
        return $this->belongsTo('App\Nfimposto');
    }

    function nfoperacao()
    {
        return $this->belongsTo('App\Nfoperacao');
    }

    function setor()
    {
        return $this->belongsTo('App\Setor');
    }

    function produto()
    {
        return $this->belongsTo('App\Produto', 'cprod');
    }

}
