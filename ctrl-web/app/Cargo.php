<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Cargo
 *
 * @property string|null $ATIVO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cargo whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cargo whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cargo whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cargo whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cargo whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cargo whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Cargo extends Model
{

    protected $fillable = ['descricao', 'ativo', 'grupo_id'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

}
