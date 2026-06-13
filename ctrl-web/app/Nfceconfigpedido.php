<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Nfceconfigpedido
 *
 * @property string|null $CREATED_AT
 * @property int $ID
 * @property int $NFGRUPOFISCAL_ID
 * @property int $NFOPERACAO_ID
 * @property int $NFOPERACAONOVA_ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Nfgrupofiscal $nfgrupofiscal
 * @property-read \App\Nfoperacao $nfoperacao
 * @property-read \App\Nfoperacao $nfoperacaonova
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfceconfigpedido whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfceconfigpedido whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfceconfigpedido whereNFGRUPOFISCALID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfceconfigpedido whereNFOPERACAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfceconfigpedido whereNFOPERACAONOVAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfceconfigpedido whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Nfceconfigpedido extends Model
{
    protected $fillable = ['nfoperacao_id', 'nfoperacaonova_id', 'nfgrupofiscal_id'];

    public function nfgrupofiscal()
    {
    	return $this->belongsTo('App\Nfgrupofiscal');
    }
    public function nfoperacao()
    {
    	return $this->belongsTo('App\Nfoperacao');
    }
    public function nfoperacaonova()
    {
    	return $this->belongsTo('App\Nfoperacao', 'nfoperacaonova_id');
    }
}
