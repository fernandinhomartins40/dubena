<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Nfrecebidavolume
 *
 * @property string|null $CREATED_AT
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int $NFRECEBIDA_ID
 * @property float $PESOBRUTO
 * @property float $PESOLIQUIDO
 * @property int $PRODUTO_ID
 * @property string $PRODUTOESPECIE
 * @property string $PRODUTOMARCA
 * @property int $QUANTIDADEVOLUME
 * @property string|null $UPDATED_AT
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Nfrecebida $nfRecebida
 * @property-read \App\Produto $produto
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidavolume whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidavolume whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidavolume whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidavolume whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidavolume whereNFRECEBIDAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidavolume wherePESOBRUTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidavolume wherePESOLIQUIDO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidavolume wherePRODUTOESPECIE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidavolume wherePRODUTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidavolume wherePRODUTOMARCA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidavolume whereQUANTIDADEVOLUME($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidavolume whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Nfrecebidavolume extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'nfrecebida_id', 'produto_id',
        'quantidadevolume', 'produtoespecie', 'produtomarca', 'pesoliquido', 'pesobruto'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function nfRecebida()
    {
        return $this->belongsTo('App\Nfrecebida');
    }

    public function produto()
    {
        return $this->belongsTo('App\Produto');
    }

}
