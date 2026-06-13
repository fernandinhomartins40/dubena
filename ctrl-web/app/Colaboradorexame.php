<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Colaboradorexame
 *
 * @property string $ALERTA
 * @property int $COLABORADOR_ID
 * @property string|null $CREATED_AT
 * @property string|null $DATA
 * @property string|null $DATAVENCIMENTO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int $TIPOEXAME_ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Colaborador $colaborador
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Tipoexame $tipoexame
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorexame whereALERTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorexame whereCOLABORADORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorexame whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorexame whereDATA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorexame whereDATAVENCIMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorexame whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorexame whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorexame whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorexame whereTIPOEXAMEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorexame whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Colaboradorexame extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'colaborador_id', 'tipoexame_id', 'data',
        'datavencimento', 'alerta'];

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

    public function tipoexame()
    {
        return $this->belongsTo('App\Tipoexame');
    }

}
