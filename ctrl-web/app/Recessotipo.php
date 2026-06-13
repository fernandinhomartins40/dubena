<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Recessotipo
 *
 * @property string|null $ATIVO
 * @property string $COR
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string $LEGENDA
 * @property string|null $UPDATED_AT
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Recessotipo whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Recessotipo whereCOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Recessotipo whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Recessotipo whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Recessotipo whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Recessotipo whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Recessotipo whereLEGENDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Recessotipo whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Recessotipo extends Model
{


    protected $fillable = ['grupo_id', 'descricao', 'ativo', 'cor', 'legenda'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

}
