<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Menuuser
 *
 * @property string $ALERTA
 * @property string $BAIXAR
 * @property string|null $CREATED_AT
 * @property string $CRIAR
 * @property string $DELETAR
 * @property string $EDITAR
 * @property int|null $EMPRESA_ID
 * @property int $ID
 * @property int $MENU_ID
 * @property string|null $UPDATED_AT
 * @property int $USER_ID
 * @property string $VISUALIZAR
 * @property-read \App\Empresa $empresa
 * @property-read \App\Menu $menu
 * @property-read \App\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Menuuser whereALERTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Menuuser whereBAIXAR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Menuuser whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Menuuser whereCRIAR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Menuuser whereDELETAR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Menuuser whereEDITAR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Menuuser whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Menuuser whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Menuuser whereMENUID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Menuuser whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Menuuser whereUSERID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Menuuser whereVISUALIZAR($value)
 * @mixin \Eloquent
 */
class Menuuser extends Model
{
    protected $fillable = ['user_id','menu_id','empresa_id','visualizar','criar','editar','baixar','alerta','deletar'];

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function user()
    {
        return $this->belongsTo('App\User');
    }

    public function menu()
    {
        return $this->belongsTo('App\Menu');
    }

    public function getvisualizar($emp_id,$menu_id)
    {
        return $this->belongsTo('App\User')->where(['visualizar'=>1,'empresa_id'=>$emp_id,'menu_id',$menu_id])->orderBy('menu_id');
    }

    public function getCriar($user_id,$emp_id)
    {
        return $this->where(['user_id'=>$user_id,'criar'=>1,'empresa_id'=>$emp_id])->orderBy('menu_id')->get();
    }

    public function getEditar($user_id,$emp_id)
    {
        return $this->where(['user_id'=>$user_id,'editar'=>1,'empresa_id'=>$emp_id])->orderBy('menu_id')->get();
    }

    public function getDeletar($user_id,$emp_id)
    {
        return $this->where(['user_id'=>$user_id,'deletar'=>1,'empresa_id'=>$emp_id])->orderBy('menu_id')->get();
    }
}
