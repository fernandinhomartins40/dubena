<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Spedcontribuicoescredito
 *
 * @property string|null $CNPJ_SUC
 * @property string|null $COD_CRED
 * @property string|null $CREATED_AT
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $ORIG_CRED
 * @property string|null $PER_APU_CRED
 * @property int|null $REGISTRO
 * @property float|null $SD_CRED_DISP_EFD
 * @property float|null $SLD_CRED_FIM
 * @property string|null $UPDATED_AT
 * @property float|null $VL_CRED_APU
 * @property float|null $VL_CRED_DCOMP_EFD
 * @property float|null $VL_CRED_DCOMP_PA_ANT
 * @property float|null $VL_CRED_DESC_EFD
 * @property float|null $VL_CRED_DESC_PA_ANT
 * @property float|null $VL_CRED_EXT_APU
 * @property float|null $VL_CRED_OUT
 * @property float|null $VL_CRED_PER_EFD
 * @property float|null $VL_CRED_PER_PA_ANT
 * @property float|null $VL_CRED_TRANS
 * @property float|null $VL_TOT_CRED_APU
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $grupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedcontribuicoescredito whereCNPJSUC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedcontribuicoescredito whereCODCRED($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedcontribuicoescredito whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedcontribuicoescredito whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedcontribuicoescredito whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedcontribuicoescredito whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedcontribuicoescredito whereORIGCRED($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedcontribuicoescredito wherePERAPUCRED($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedcontribuicoescredito whereREGISTRO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedcontribuicoescredito whereSDCREDDISPEFD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedcontribuicoescredito whereSLDCREDFIM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedcontribuicoescredito whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedcontribuicoescredito whereVLCREDAPU($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedcontribuicoescredito whereVLCREDDCOMPEFD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedcontribuicoescredito whereVLCREDDCOMPPAANT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedcontribuicoescredito whereVLCREDDESCEFD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedcontribuicoescredito whereVLCREDDESCPAANT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedcontribuicoescredito whereVLCREDEXTAPU($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedcontribuicoescredito whereVLCREDOUT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedcontribuicoescredito whereVLCREDPEREFD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedcontribuicoescredito whereVLCREDPERPAANT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedcontribuicoescredito whereVLCREDTRANS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedcontribuicoescredito whereVLTOTCREDAPU($value)
 * @mixin \Eloquent
 */
class Spedcontribuicoescredito extends Model
{
    protected $fillable = ["cnpj_suc", "cod_cred", "empresa_id", "grupo_id", "orig_cred", "per_apu_cred", "registro", 
        "sd_cred_disp_efd", "sld_cred_fim", "vl_cred_apu", "vl_cred_dcomp_efd", "vl_cred_dcomp_pa_ant", "vl_cred_desc_efd", 
        "vl_cred_desc_pa_ant", "vl_cred_ext_apu", "vl_cred_out", "vl_cred_per_efd", "vl_cred_per_pa_ant", "vl_cred_trans", "vl_tot_cred_apu"];

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function grupo()
    {
        return $this->belongsTo('App\Empresasgrupo');
    }

}
