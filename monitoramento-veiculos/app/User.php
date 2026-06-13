<?php

namespace App;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{

    use HasApiTokens, Notifiable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'empresa_id', 'grupo_id', 'foto', 'ativo', 'access_token', 'client_id'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    // FASE 1 (segurança): 'password' adicionado ao hidden para nunca vazar o
    // hash em serializações/JSON (defesa em profundidade — ver achado S5).
    protected $hidden = [
        'remember_token',
        'password',
    ];

    public static $login_validation_rules = [
      'email' => 'required|exists:users',
      'password' => 'required|min:8' // FASE 1: era min:4 (S6)
    ];

    public function empresas(){
      return $this->belongsToMany('App\Empresa')->withTimestamps();
    }

    public function getEmpresaListAttribute(){
      return $this->empresas()->lists('id');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }
    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }
    public function menus(){
      return $this->belongsToMany('App\Menu')->withTimestamps();
    }
    
}
