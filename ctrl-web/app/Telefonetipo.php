<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Telefonetipo
 *
 * @property string|null $ATIVO
 * @property string $CELULAR
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Telefonetipo whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Telefonetipo whereCELULAR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Telefonetipo whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Telefonetipo whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Telefonetipo whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Telefonetipo whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Telefonetipo whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Telefonetipo extends Model
{

    protected $fillable = ['grupo_id', 'descricao', 'ativo', 'celular'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

}
