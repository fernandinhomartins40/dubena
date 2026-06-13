<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Nfsituacao
 *
 * @property string|null $ATIVO
 * @property string|null $CREATED_AT
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string $MSGERRORECEITA
 * @property string|null $UPDATED_AT
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfemitida[] $nfEmitida
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfsituacao whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfsituacao whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfsituacao whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfsituacao whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfsituacao whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfsituacao whereMSGERRORECEITA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfsituacao whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Nfsituacao extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'msgerroreceita', 'ativo'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function nfEmitida()
    {
        return $this->hasMany('App\Nfemitida');
    }

}
