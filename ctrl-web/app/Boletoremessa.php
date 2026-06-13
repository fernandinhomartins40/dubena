<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Boletoremessa
 *
 * @property string $CANCELADO
 * @property int $CONTA_ID
 * @property string|null $CREATED_AT
 * @property string $DATAHORA
 * @property string|null $EFETIVADO
 * @property int $EMPRESA_ID
 * @property string $GEROUREMESSA
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int $NUMEROSEQUENCIA
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Boletoremessafinanceiro[] $boletoremessaFinanceiro
 * @property-read \App\Conta $conta
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessa whereCANCELADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessa whereCONTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessa whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessa whereDATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessa whereEFETIVADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessa whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessa whereGEROUREMESSA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessa whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessa whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessa whereNUMEROSEQUENCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletoremessa whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Boletoremessa extends Model
{
    protected $fillable = ['grupo_id', 'empresa_id', 'conta_id', 'datahora', 'numerosequencia', 'cancelado', 'gerouremessa', 'efetivado', 'updated_at'];

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

    public function boletoremessaFinanceiro()
    {
        return $this->hasMany('App\Boletoremessafinanceiro');
    }

}
