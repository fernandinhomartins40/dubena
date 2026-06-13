<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Nfgrupofiscal
 *
 * @property string|null $ATIVO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupos
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfimposto[] $nfImposto
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfceconfigpedido[] $nfceconfigpedido
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfgrupofiscal whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfgrupofiscal whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfgrupofiscal whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfgrupofiscal whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfgrupofiscal whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfgrupofiscal whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfgrupofiscal whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Nfgrupofiscal extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'descricao', 'ativo'];

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

    public function nfceconfigpedido()
    {
        return $this->hasMany('App\Nfceconfigpedido');
    }
}
