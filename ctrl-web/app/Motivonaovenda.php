<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Motivonaovenda
 *
 * @property string|null $ATIVO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Motivonaovenda whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Motivonaovenda whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Motivonaovenda whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Motivonaovenda whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Motivonaovenda whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Motivonaovenda whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Motivonaovenda extends Model
{


    protected $fillable = ['grupo_id', 'descricao', 'ativo'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

}
