<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Estoquefisico
 *
 * @property string|null $CREATED_AT
 * @property string $DATACOMPETENCIA
 * @property string $EFETIVADO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property int $USER_ID
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Estoquefechamento $estoquefechamento
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Estoquefisicosetor[] $estoquefisicosetor
 * @property-read \App\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefisico whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefisico whereDATACOMPETENCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefisico whereEFETIVADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefisico whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefisico whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefisico whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefisico whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefisico whereUSERID($value)
 * @mixin \Eloquent
 */
class Estoquefisico extends Model
{
    protected $fillable = ['grupo_id', 'empresa_id',
        'datacompetencia', 'efetivado', 'user_id'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function estoquefechamento()
    {
        return $this->belongsTo('App\Estoquefechamento');
    }

    public function estoquefisicosetor()
    {
        return $this->hasMany('App\Estoquefisicosetor');
    }

    public function user()
    {
        return $this->belongsTo('App\User');
    }

}
