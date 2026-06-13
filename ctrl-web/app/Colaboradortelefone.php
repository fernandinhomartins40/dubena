<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Colaboradortelefone
 *
 * @property int $COLABORADOR_ID
 * @property string|null $CREATED_AT
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string $TELEFONE
 * @property int $TELEFONETIPO_ID
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Colaborador[] $colaborador
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Telefonetipo $telefonetipo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradortelefone whereCOLABORADORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradortelefone whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradortelefone whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradortelefone whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradortelefone whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradortelefone whereTELEFONE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradortelefone whereTELEFONETIPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradortelefone whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Colaboradortelefone extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'colaborador_id', 'telefone', 'telefonetipo_id'];

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
        return $this->hasMany('App\Colaborador');
    }

    public function telefonetipo()
    {
        return $this->belongsTo('App\Telefonetipo');
    }

}
