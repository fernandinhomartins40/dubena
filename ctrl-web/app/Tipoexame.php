<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Tipoexame
 *
 * @property string|null $ADMISSIONAL
 * @property string|null $ATIVO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipoexame whereADMISSIONAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipoexame whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipoexame whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipoexame whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipoexame whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipoexame whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipoexame whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipoexame whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Tipoexame extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'descricao', 'ativo', 'admissional'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

}
