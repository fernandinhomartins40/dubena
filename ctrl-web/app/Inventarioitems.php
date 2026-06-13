<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Inventarioitems
 *
 * @property string|null $CREATED_AT
 * @property int $ID
 * @property int $INVENTARIO_ID
 * @property int $PRODUTO_ID
 * @property float|null $QUANTIDADE
 * @property string|null $UPDATED_AT
 * @property float|null $VALORUNITARIO
 * @property-read \App\Inventario $inventario
 * @property-read \App\Produto $produto
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Inventarioitems whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Inventarioitems whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Inventarioitems whereINVENTARIOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Inventarioitems wherePRODUTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Inventarioitems whereQUANTIDADE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Inventarioitems whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Inventarioitems whereVALORUNITARIO($value)
 * @mixin \Eloquent
 */
class Inventarioitems extends Model
{
    protected $fillable = ["inventario_id","produto_id","quantidade","valorunitario","created_at","updated_at"];

    public function inventario()
    {
        return $this->belongsTo('App\Inventario','inventario_id');
    }

    public function produto()
    {
        return $this->belongsTo('App\Produto','produto_id');
    }
}
