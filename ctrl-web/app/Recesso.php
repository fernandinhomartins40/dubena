<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Recesso
 *
 * @property string|null $CREATED_AT
 * @property string $DATAFINAL
 * @property string $DATAINICIO
 * @property string $DESCRICAO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int|null $RECESSO_ID
 * @property int $TIPO_ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Recesso $recesso
 * @property-read \App\Recessotipo $tipo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Recesso whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Recesso whereDATAFINAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Recesso whereDATAINICIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Recesso whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Recesso whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Recesso whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Recesso whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Recesso whereRECESSOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Recesso whereTIPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Recesso whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Recesso extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'descricao', 'tipo_id', 'datainicio',
        'datafinal', 'recesso_id'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function tipo()
    {
        return $this->belongsTo('App\Recessotipo');
    }

    public function recesso()
    {
        return $this->belongsTo('App\Recesso', 'recesso_id');
    }

}
