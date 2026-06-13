<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Clientecontatotipo
 *
 * @property string|null $ATIVO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Clientecontato[] $clientecontatos
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientecontatotipo whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientecontatotipo whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientecontatotipo whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientecontatotipo whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientecontatotipo whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientecontatotipo whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Clientecontatotipo extends Model
{


    protected $fillable = ['grupo_id', 'descricao', 'ativo'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function clientecontatos()
    {
        return $this->hasMany('App\Clientecontato');
    }

}
