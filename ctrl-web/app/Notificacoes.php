<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Notificacoes
 *
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int $IDENTIFICADOR
 * @property string $TELA
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Empresa[] $empresa
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\EmpresasGrupo[] $grupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Notificacoes whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Notificacoes whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Notificacoes whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Notificacoes whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Notificacoes whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Notificacoes whereIDENTIFICADOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Notificacoes whereTELA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Notificacoes whereUPDATEDAT($value)
 * @mixin \Eloquent
 * @property string $APPNOTIFICATION
 * @property int|null $DANGERLEVEL
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Notificacoes whereAPPNOTIFICATION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Notificacoes whereDANGERLEVEL($value)
 */
class Notificacoes extends Model
{
    protected $fillable = [
        "grupo_id", "empresa_id", "descricao",
        "identificador", "tela", "dangerlevel", "appnotification"
    ];

    public function grupo()
    {
        return $this->belongsToMany('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsToMany('App\Empresa');
    }
}
