<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\CupomFiscalItem
 *
 * @property string|null $cean
 * @property string|null $cest
 * @property string $cfop
 * @property int $nitem
 * @property int $cprod
 * @property string|null $created_at
 * @property string|null $cstcofins
 * @property string $csticms
 * @property string|null $cstpis
 * @property int $cupomfiscal_id
 * @property int $empresa_id
 * @property int $grupo_id
 * @property string|null $icmsorig
 * @property int $id
 * @property string $indregra
 * @property float|null $infadprod
 * @property string|null $ncm
 * @property int $nfimposto_id
 * @property float|null $pcofins
 * @property float|null $picms
 * @property float|null $ppis
 * @property float|null $qbcprodcofins
 * @property float|null $qbcprodpis
 * @property float $qcom
 * @property string $ucom
 * @property string|null $updated_at
 * @property float|null $valiqprodcofins
 * @property float|null $valiqprodpis
 * @property float|null $vbccofins
 * @property float|null $vbcpis
 * @property float|null $vcofins
 * @property float|null $vdesc
 * @property float|null $vicms
 * @property float|null $vitem
 * @property float|null $vitem12741
 * @property float|null $voutro
 * @property float|null $vpis
 * @property float|null $vprod
 * @property float|null $vratacr
 * @property float|null $vratdesc
 * @property integer $nfoperacao_id
 * @property integer $setor_id
 * @property float $vuncom
 * @property string $xprod
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereCEAN($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereCEST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereCFOP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereCPROD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereCSTCOFINS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereCSTICMS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereCSTPIS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereCUPOMFISCALID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereICMSORIG($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereINDREGRA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereINFADPROD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereNCM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereNFIMPOSTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem wherePCOFINS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem wherePICMS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem wherePPIS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereQBCPRODCOFINS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereQBCPRODPIS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereQCOM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereUCOM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereVALIQPRODCOFINS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereVALIQPRODPIS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereVBCCOFINS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereVBCPIS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereVCOFINS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereVDESC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereVICMS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereVITEM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereVITEM12741($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereVOUTRO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereVPIS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereVPROD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereVRATACR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereVRATDESC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereVUNCOM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereXPROD($value)
 * @mixin \Eloquent
 * @property string|null $CEAN
 * @property string|null $CEST
 * @property string $CFOP
 * @property int $CPROD
 * @property string|null $CREATED_AT
 * @property string|null $CSTCOFINS
 * @property string $CSTICMS
 * @property string|null $CSTPIS
 * @property int $CUPOMFISCAL_ID
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property string|null $ICMSORIG
 * @property int $ID
 * @property string $INDREGRA
 * @property float|null $INFADPROD
 * @property string|null $NCM
 * @property int $NFIMPOSTO_ID
 * @property int $NFOPERACAO_ID
 * @property int $NITEM
 * @property float|null $PCOFINS
 * @property float|null $PICMS
 * @property float|null $PPIS
 * @property float|null $QBCPRODCOFINS
 * @property float|null $QBCPRODPIS
 * @property float $QCOM
 * @property int $SETOR_ID
 * @property string $UCOM
 * @property string|null $UPDATED_AT
 * @property float|null $VALIQPRODCOFINS
 * @property float|null $VALIQPRODPIS
 * @property float|null $VBCCOFINS
 * @property float|null $VBCPIS
 * @property float|null $VCOFINS
 * @property float|null $VDESC
 * @property float|null $VICMS
 * @property float|null $VITEM
 * @property float|null $VITEM12741
 * @property float|null $VOUTRO
 * @property float|null $VPIS
 * @property float|null $VPROD
 * @property float|null $VRATACR
 * @property float|null $VRATDESC
 * @property float $VUNCOM
 * @property string $XPROD
 * @property string $cprodanp
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereNFOPERACAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereNITEM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereSETORID($value)
 * @property string|null $CPRODANP
 * @property float|null $VAPROXTRIBEST
 * @property float|null $VAPROXTRIBFED
 * @property float|null $VAPROXTRIBMUN
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereCPRODANP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereVAPROXTRIBEST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereVAPROXTRIBFED($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalItem whereVAPROXTRIBMUN($value)
 */
class CupomFiscalItem extends Model
{
    protected $table = "cuponsfiscaisitens";

    protected $fillable = [
        "vprod", "vitem", "vratdesc", "vratacr", "vicms", "vpis", "vcofins", "cean", "ncm", "cest", "vdesc", "voutro",
        "vitem12741", "infadprod", "xprod", "cfop", "ucom", "qcom", "vuncom", "indregra", "csticms", "cstpis",
        "cstcofins", "icmsorig", "picms", "vbcpis", "ppis", "qbcprodpis", "valiqprodpis", "vbccofins", "pcofins",
        "qbcprodcofins", "valiqprodcofins", 'cupomfiscal_id', 'grupo_id', 'empresa_id', 'nfimposto_id', "cprod",
        "nfoperacao_id", "setor_id", "nitem", "cprodanp", "vaproxtribest", "vaproxtribmun", "vaproxtribfed"
    ];
}
