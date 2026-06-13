<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Parentesco
 *
 * @property string|null $ATIVO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Clienteconveniodependente[] $clienteConvenioPendente
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Parentesco whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Parentesco whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Parentesco whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Parentesco whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Parentesco whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Parentesco whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Parentesco extends Model
{

    protected $fillable = ['descricao', 'ativo', 'grupo_id'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function clienteConvenioPendente()
    {
        return $this->hasMany('App\Clienteconveniodependente');
    }

}
