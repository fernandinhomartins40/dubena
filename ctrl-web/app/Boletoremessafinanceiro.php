<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Boletoremessafinanceiro
 *
 * @property int $BOLETOREMESSA_ID
 * @property string $CANCELADO
 * @property int $CONTA_ID
 * @property string|null $CREATED_AT
 * @property string $DATAHORA
 * @property int $EMPRESA_ID
 * @property int $FINANCEIRO_ID
 * @property int $FINANCEIROPARCELA_ID
 * @property string $GEROUREMESSA
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string $NOSSONUMERO
 * @property int $NUMEROSEQUENCIA
 * @property string $PROTESTAR
 * @property int $PROTESTARDIAS
 * @property string|null $UPDATED_AT
 * @property-read \App\Boletoremessa $boletoRemessa
 * @property-read \App\Conta $conta
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Financeiro $financeiro
 * @property-read \App\Financeiroparcela $financeiroParcela
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessafinanceiro whereBOLETOREMESSAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessafinanceiro whereCANCELADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessafinanceiro whereCONTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessafinanceiro whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessafinanceiro whereDATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessafinanceiro whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessafinanceiro whereFINANCEIROID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessafinanceiro whereFINANCEIROPARCELAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessafinanceiro whereGEROUREMESSA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessafinanceiro whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessafinanceiro whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessafinanceiro whereNOSSONUMERO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessafinanceiro whereNUMEROSEQUENCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessafinanceiro wherePROTESTAR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessafinanceiro wherePROTESTARDIAS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessafinanceiro whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Boletoremessafinanceiro extends Model
{
    
    protected $fillable = ['grupo_id', 'empresa_id', 'boletoremessa_id', 'financeiroparcela_id', 'financeiro_id', 
    					 'conta_id', 'datahora', 'numerosequencia', 'nossonumero', 'cancelado', 'gerouremessa', 'protestar', 'protestardias'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function conta()
    {
        return $this->belongsTo('App\Conta');
    }

    public function boletoRemessa()
    {
        return $this->belongsTo('App\Boletoremessa', 'boletoremessa_id');
    }

    public function financeiro()
    {
        return $this->belongsTo('App\financeiro');
    }

    public function financeiroParcela()
    {
        return $this->belongsTo('App\Financeiroparcela', 'financeiroparcela_id');
    }
}
