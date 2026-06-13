<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Turno
 *
 * @property string|null $ATIVO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Turno whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Turno whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Turno whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Turno whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Turno whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Turno whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Turno whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Turno extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'descricao', 'ativo'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

}
