<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Condicaopagamentoparcela
 *
 * @property int $CONDICAOPAGAMENTO_ID
 * @property string|null $CREATED_AT
 * @property int $DIAS
 * @property int $ID
 * @property float $PERCENTUALVALOR
 * @property string|null $UPDATED_AT
 * @property-read \App\Condicaopagamento $condicaoPagamento
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Condicaopagamentoparcela whereCONDICAOPAGAMENTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Condicaopagamentoparcela whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Condicaopagamentoparcela whereDIAS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Condicaopagamentoparcela whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Condicaopagamentoparcela wherePERCENTUALVALOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Condicaopagamentoparcela whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Condicaopagamentoparcela extends Model
{

    protected $fillable = ['condicaopagamento_id', 'dias', 'percentualvalor'];

    public function condicaoPagamento()
    {
        return $this->belongsTo('App\Condicaopagamento');
    }

}
