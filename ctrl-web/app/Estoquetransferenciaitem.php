<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Estoquetransferenciaitem
 *
 * @property string|null $CREATED_AT
 * @property int $ESTOQUETRANSFERENCIA_ID
 * @property int $ID
 * @property int $PRODUTO_ID
 * @property float $QUANTIDADE
 * @property string|null $UPDATED_AT
 * @property-read \App\Estoquetransferencia $estoqueTransferencia
 * @property-read \App\Produto $produto
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquetransferenciaitem whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquetransferenciaitem whereESTOQUETRANSFERENCIAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquetransferenciaitem whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquetransferenciaitem wherePRODUTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquetransferenciaitem whereQUANTIDADE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquetransferenciaitem whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Estoquetransferenciaitem extends Model
{

    protected $fillable = ['estoquetransferencia_id', 'produto_id', 'quantidade'];

    public function estoqueTransferencia()
    {
        return $this->belongsTo('App\Estoquetransferencia');
    }

    public function produto()
    {
        return $this->belongsTo('App\Produto');
    }

}
