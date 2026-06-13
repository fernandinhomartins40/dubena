<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Nfrecebidaitem
 *
 * @property string|null $CEAN
 * @property string|null $CEANTRIB
 * @property string|null $CEST
 * @property int $CFOP
 * @property string $CODIGOLOTE
 * @property string $CONTROLAESTOQUE
 * @property string $CPROD
 * @property string|null $CPRODANP
 * @property string|null $CREATED_AT
 * @property int $CST
 * @property int $CSTCOFINS
 * @property int|null $CSTIPI
 * @property int $CSTPIS
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int $INDTOT
 * @property int|null $MODBC
 * @property int|null $MODBCST
 * @property int|null $MOTDESICMS
 * @property int|null $MOVIMENTAESTOQUE
 * @property string $NCM
 * @property int $NFIMPOSTO_ID
 * @property int $NFOPERACAO_ID
 * @property int $NFRECEBIDA_ID
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
 * @property float $VBCCOFINS
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
 * @property-read \App\Nfimposto $nfImposto
 * @property-read \App\Nfrecebida $nfRecebida
 * @property-read \App\Nfoperacao $nfoperacao
 * @property-read \App\Produto $produto
 * @property-read \App\Setor $setor
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereCEAN($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereCEANTRIB($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereCEST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereCFOP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereCODIGOLOTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereCONTROLAESTOQUE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereCPROD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereCPRODANP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereCST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereCSTCOFINS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereCSTIPI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereCSTPIS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereINDTOT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereMODBC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereMODBCST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereMOTDESICMS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereMOVIMENTAESTOQUE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereNCM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereNFIMPOSTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereNFOPERACAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereNFRECEBIDAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereNITEMPED($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereORIG($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem wherePBCOP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem wherePCOFINS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem wherePCREDSN($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem wherePDIF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem wherePESOB($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem wherePESOL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem wherePFCP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem wherePFCPST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem wherePFCPSTRET($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem wherePICMS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem wherePICMSST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem wherePIPI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem wherePMVAST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem wherePPIS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem wherePREDBC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem wherePREDBCST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem wherePST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereQBCPROD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereQCOM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereQESTOQUE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereQTRIB($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereQVOL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereSETORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereTAGCOFINS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereTAGICMS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereTAGIPI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereTAGPIS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereUCOM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereUTRIB($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVALIQPROD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVBC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVBCCOFINS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVBCFCP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVBCFCPST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVBCFCPSTRET($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVBCIPI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVBCPIS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVBCST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVBCSTDEST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVBCSTRET($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVCIDE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVCOFINS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVCREDICMSSN($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVDESC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVFCP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVFCPST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVFCPSTRET($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVFRETE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVICMS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVICMSDESON($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVICMSDIF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVICMSOP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVICMSST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVICMSSTRET($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVIPI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVOUTRO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVPIS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVPROD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVSEG($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVUNCOM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVUNTRIB($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereXPROD($value)
 * @mixin \Eloquent
 * @property float $PGLP
 * @property float $PGNI
 * @property float $PGNN
 * @property float $VPART
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem wherePGLP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem wherePGNI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem wherePGNN($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaitem whereVPART($value)
 */
class Nfrecebidaitem extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'nfrecebida_id', 'nfimposto_id',
        'nfoperacao_id', 'setor_id', 'cprod', 'cean', 'xprod', 'ncm', 'cfop', 'ucom', 'qcom',
        'vuncom', 'vprod', 'ceantrib', 'utrib', 'qtrib', 'vuntrib', 'indTot', 'tagicms', 'orig',
        'cst', 'modbc', 'vbc', 'picms', 'vicms', 'vbcstret', 'vicmsstret', 'tagpis', 'cstpis',
        'vbcpis', 'ppis', 'vpis', 'tagcofins', 'pcofins', 'vcofins',
        'qestoque', 'tagipi', 'cstipi', 'vbcipi', 'pipi', 'vipi', 'vdesc', 'vfrete',
        'codigolote', 'picmsst', 'modbcst', 'pmvast', 'predbcst', 'vbcst',
        'vicmsst', 'predbc', 'vicmsdeson', 'motdesicms', 'vicmsdif', 'pdif', 'vicmsop', 'pbcop',
        'vbcstdest', 'pcredsn', 'vcredicmssn', 'pfcp', 'vfcp', 'vbcfcp', 'vbcfcpst', 'pfcpst',
        'vfcpst', 'pst', 'vbcfcpstret', 'pfcpstret', 'vfcpstret', 'cstcofins', 'vbccofins', 'vseg',
        'voutro', 'nitemped', 'cest', 'controlaestoque', 'cprodanp', 'qbcprod', 'valiqprod', 'vcide',
        'qvol', 'pesol', 'pesob', 'movimentaestoque', 'pGLP', 'pGNn', 'pGNi', 'vPart'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function nfRecebida()
    {
        return $this->belongsTo('App\Nfrecebida');
    }

    public function nfImposto()
    {
        return $this->belongsTo('App\Nfimposto');
    }

    public function nfoperacao()
    {
        return $this->belongsTo('App\Nfoperacao');
    }

    public function setor()
    {
        return $this->belongsTo('App\Setor');
    }

    function produto()
    {
        return $this->belongsTo('App\Produto', 'cprod');
    }

}
