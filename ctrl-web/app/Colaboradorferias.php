<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Colaboradorferias
 *
 * @property int $COLABORADOR_ID
 * @property string|null $CREATED_AT
 * @property string|null $DATAINICIO
 * @property int $DIAS
 * @property int $EMPRESA_ID
 * @property string $GOZADA
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Colaborador $colaborador
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorferias whereCOLABORADORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorferias whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorferias whereDATAINICIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorferias whereDIAS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorferias whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorferias whereGOZADA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorferias whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorferias whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorferias whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Colaboradorferias extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'colaborador_id', 'datainicio', 'dias', 'gozada'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function colaborador()
    {
        return $this->belongsTo('App\Colaborador');
    }

}
