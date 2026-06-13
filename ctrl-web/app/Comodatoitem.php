<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Comodatoitem
 *
 * @property int $COMODATO_ID
 * @property string|null $CREATED_AT
 * @property int $ID
 * @property int $PRODUTO_ID
 * @property float $QUANTIDADE
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Comodato[] $comodato
 * @property-read \App\Produto $produto
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comodatoitem whereCOMODATOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comodatoitem whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comodatoitem whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comodatoitem wherePRODUTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comodatoitem whereQUANTIDADE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comodatoitem whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Comodatoitem extends Model
{

    protected $fillable = [
        'comodato_id',
        'produto_id',
        'quantidade',
    ];

    public function produto()
    {
        return $this->belongsTo('App\Produto');
    }

    public function comodato()
    {
        return $this->hasMany('App\Comodato');
    }

}
