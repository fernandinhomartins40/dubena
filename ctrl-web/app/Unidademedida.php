<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Unidademedida
 *
 * @property string|null $ATIVO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string $SIGLA
 * @property string|null $UPDATED_AT
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Unidademedida whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Unidademedida whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Unidademedida whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Unidademedida whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Unidademedida whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Unidademedida whereSIGLA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Unidademedida whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Unidademedida extends Model
{


    protected $fillable = ['grupo_id', 'descricao', 'sigla', 'ativo'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

}
