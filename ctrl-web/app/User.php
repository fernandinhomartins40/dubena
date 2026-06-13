<?php

namespace App;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * App\User
 *
 * @property string|null $ATIVO
 * @property int|null $COLABORADOR_ID
 * @property string|null $CREATED_AT
 * @property string $EMAIL
 * @property int|null $EMPRESA_ID
 * @property mixed|null $FOTO
 * @property int $ID
 * @property string|null $IDENTIFICADORCHAMADA
 * @property string|null $IMPRESSORATERMICA
 * @property string|null $NAME
 * @property string $password
 * @property string|null $REMEMBER_TOKEN
 * @property string $SUPPORT
 * @property int|null $TIPO_ID
 * @property string|null $UPDATED_AT
 * @property string|null $USAANDROID
 * @property string|null $USARASTREAMENTO
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Atualizacaoprecos[] $atualizacaoPreco
 * @property-read \Illuminate\Database\Eloquent\Collection|\Laravel\Passport\Client[] $clients
 * @property-read \App\Colaborador $colaborador
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Contauser[] $contas
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Contauser[] $contasAtivas
 * @property-read \App\Empresa $empresa
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Empresa[] $empresas
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read mixed $empresa_list
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Menu[] $menus
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Menuuser[] $menuuser
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection|\Illuminate\Notifications\DatabaseNotification[] $notifications
 * @property-read \App\Role $role
 * @property-read \Illuminate\Database\Eloquent\Collection|\Laravel\Passport\Token[] $tokens
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereCOLABORADORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereEMAIL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereFOTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereIDENTIFICADORCHAMADA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereIMPRESSORATERMICA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereNAME($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereREMEMBERTOKEN($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereSUPPORT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereTIPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereUSAANDROID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereUSARASTREAMENTO($value)
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Oauthclient[] $oauth
 */
class User extends Authenticatable
{

    use HasApiTokens, Notifiable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'empresa_id', 'grupo_id', 'foto',
        'colaborador_id', 'support', 'ativo','usaandroid', 'identificadorchamada',
        'usarastreamento', 'impressoratermica', 'tipo_id'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden =
    [
         'remember_token',
    ];

    public static $login_validation_rules =
    [
      'email' => 'required|exists:users',
      'password' => 'required|min:8' // FASE 1 (segurança — S6): era min:4
    ];

    public function empresas()
    {
      return $this->belongsToMany('App\Empresa')->withTimestamps();
    }

    public function getEmpresaListAttribute(){
      return $this->empresas()->pluck('id');
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

    public function colaborador()
    {
        return $this->belongsTo('App\Colaborador');
    }

    public function contas()
    {
      return $this->hasMany('App\Contauser');
    }

    public function contasAtivas()
    {
      return $this->hasMany('App\Contauser')->join('contas', 'contas.id', '=', 'contausers.conta_id')->where('ativo', 1);
    }

    public function menuuser()
    {
      return $this->hasMany('App\Menuuser');
    }

    public function atualizacaoPreco()
    {
      return $this->hasMany('App\Atualizacaoprecos');
    }

    public function role()
    {
      return $this->belongsTo('App\Role', 'tipo_id');
    }

    public function oauth()
    {
        return $this->hasMany('App\Oauthclient');
    }
}
