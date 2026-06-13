<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Clientecontatosituacao
 *
 * @property string|null $ATIVO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Clientecontato[] $clientecontatos
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientecontatosituacao whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientecontatosituacao whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientecontatosituacao whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientecontatosituacao whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientecontatosituacao whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientecontatosituacao whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Clientecontatosituacao extends Model
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
