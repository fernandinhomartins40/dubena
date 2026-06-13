<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Tipocombustivel
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
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipocombustivel whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipocombustivel whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipocombustivel whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipocombustivel whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipocombustivel whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipocombustivel whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipocombustivel whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Tipocombustivel extends Model
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
