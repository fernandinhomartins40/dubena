<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Clienteprodutosconvenio
 *
 * @property int $CLIENTE_ID
 * @property string|null $CREATED_AT
 * @property int $ID
 * @property float $PRECO
 * @property int $PRODUTO_ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Cliente $cliente
 * @property-read \App\Produto $produto
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteprodutosconvenio whereCLIENTEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteprodutosconvenio whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteprodutosconvenio whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteprodutosconvenio wherePRECO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteprodutosconvenio wherePRODUTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteprodutosconvenio whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Clienteprodutosconvenio extends Model
{
    protected $fillable = ['cliente_id', 'produto_id', 'preco'];

    public function cliente()
    {
        return $this->belongsTo('App\Cliente');
    }

    public function produto()
    {
        return $this->belongsTo('App\Produto');
    }

}
