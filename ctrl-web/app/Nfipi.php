<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Nfipi
 *
 * @property string $CODIGO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int|null $EMPRESA_ID
 * @property int|null $GRUPO_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupos
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfipi whereCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfipi whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfipi whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfipi whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfipi whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfipi whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfipi whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Nfipi extends Model
{


    protected $fillable = ['grupo_id', 'empresa_id', 'codigo', 'descricao'];

    public function empresasGrupos()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

}
