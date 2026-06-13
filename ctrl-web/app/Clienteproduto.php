<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Clienteproduto
 *
 * @property int $CLIENTE_ID
 * @property string|null $CREATED_AT
 * @property float|null $DESCONTO
 * @property int $ID
 * @property float|null $PRECO
 * @property int $PRODUTO_ID
 * @property int|null $TIPO
 * @property string|null $UPDATED_AT
 * @property-read \App\Cliente $cliente
 * @property-read \App\Produto $produto
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteproduto whereCLIENTEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteproduto whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteproduto whereDESCONTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteproduto whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteproduto wherePRECO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteproduto wherePRODUTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteproduto whereTIPO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteproduto whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Clienteproduto extends Model
{

    // ? Valores validos para a coluna descontopara
    // ? 1 - Todos, 2 - Aplicativo e 3 - Disk
    protected $fillable = [ 'produto_id', 'cliente_id', 'preco', 'desconto', 'tipo', 'descontopara'];

    public function cliente()
    {
        return $this->belongsTo('App\Cliente');
    }

    public function produto()
    {
        return $this->belongsTo('App\Produto');
    }

    public function prod()
    {
        return $this->belongsTo('App\Produto', 'produto_id')->select("id", "descricao");
    }

}
