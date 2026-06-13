<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Nficms
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
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfimpostoestado[] $nfImpostoEstado
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nficms whereCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nficms whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nficms whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nficms whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nficms whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nficms whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nficms whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Nficms extends Model
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

    function nfImpostoEstado()
    {
        return $this->hasMany('App\Nfimpostoestado');
    }

    public function nfImposto()
    {
        return $this->hasMany('App\Nfimposto');
    }

}
