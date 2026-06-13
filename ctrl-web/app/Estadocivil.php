<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Estadocivil
 *
 * @property string|null $ATIVO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int|null $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estadocivil whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estadocivil whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estadocivil whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estadocivil whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estadocivil whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estadocivil whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estadocivil whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Estadocivil extends Model
{

    protected $fillable = ['grupo_id', 'descricao', 'ativo', 'empresa_id'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

}
