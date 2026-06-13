<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Nfemitidavolume
 *
 * @property string|null $CREATED_AT
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int $NFEMITIDA_ID
 * @property float $PESOBRUTO
 * @property float $PESOLIQUIDO
 * @property int $PRODUTO_ID
 * @property string $PRODUTOESPECIE
 * @property string $PRODUTOMARCA
 * @property int $QUANTIDADEVOLUME
 * @property string|null $UPDATED_AT
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupos
 * @property-read \App\Nfemitida $nfEmitida
 * @property-read \App\Produto $produto
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidavolume whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidavolume whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidavolume whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidavolume whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidavolume whereNFEMITIDAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidavolume wherePESOBRUTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidavolume wherePESOLIQUIDO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidavolume wherePRODUTOESPECIE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidavolume wherePRODUTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidavolume wherePRODUTOMARCA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidavolume whereQUANTIDADEVOLUME($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidavolume whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Nfemitidavolume extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'nfemitida_id', 'produto_id',
        'quantidadevolume', 'produtoespecie', 'produtomarca', 'pesoliquido', 'pesobruto'];

    public function empresasGrupos()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    function produto()
    {
        return $this->belongsTo('App\Produto');
    }

    function nfEmitida()
    {
        return $this->belongsTo('App\Nfemitida');
    }

}
