<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Nfcofins
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
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfimposto[] $nfImposto
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfcofins whereCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfcofins whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfcofins whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfcofins whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfcofins whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfcofins whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfcofins whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Nfcofins extends Model
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

    public function nfImposto()
    {
        return $this->hasMany('App\Nfimposto');
    }

}
