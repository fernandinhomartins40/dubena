<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Androidmensagem
 *
 * @property int|null $ANDROID_ID
 * @property string|null $CREATED_AT
 * @property string|null $DESCRICAO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $SITUACAO
 * @property int|null $TIPO
 * @property string|null $UPDATED_AT
 * @property int|null $USER_ID
 * @property-read \App\Android $android
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Androidmensagem whereANDROIDID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Androidmensagem whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Androidmensagem whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Androidmensagem whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Androidmensagem whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Androidmensagem whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Androidmensagem whereSITUACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Androidmensagem whereTIPO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Androidmensagem whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Androidmensagem whereUSERID($value)
 * @mixin \Eloquent
 */
class Androidmensagem extends Model
{
   protected $fillable = ['grupo_id', 'empresa_id', 'android_id', 'user_id', 'descricao',
        'tipo', 'situacao'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function android()
    {
        return $this->belongsTo('App\Android');
    }

    public function user()
    {
        return $this->belongsTo('App\User');
    }

}
