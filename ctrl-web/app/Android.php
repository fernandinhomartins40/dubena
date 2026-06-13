<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Android
 *
 * @property string $ANDROIDID
 * @property string|null $ATIVO
 * @property int|null $COLABORADOR_ID
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string $REGISTRATIONID
 * @property string $SERIE
 * @property int|null $SETOR_ID
 * @property string|null $UPDATED_AT
 * @property string $URLSERVIDOR
 * @property int|null $USER_ID
 * @property-read \App\Colaborador $colaborador
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Setor $setor
 * @property-read \App\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Android whereANDROIDID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Android whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Android whereCOLABORADORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Android whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Android whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Android whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Android whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Android whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Android whereREGISTRATIONID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Android whereSERIE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Android whereSETORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Android whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Android whereURLSERVIDOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Android whereUSERID($value)
 * @mixin \Eloquent
 */
class Android extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'setor_id', 'user_id', 'descricao',
        'serie', 'androidid', 'urlservidor', 'registrationid', 'ativo', 'colaborador_id'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function setor()
    {
        return $this->belongsTo('App\Setor');
    }

    public function user()
    {
        return $this->belongsTo('App\User');
    }
    public function colaborador()
    {
        return $this->belongsTo('App\Colaborador');
    }
}
