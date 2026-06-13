<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Financeirorateio
 *
 * @property int $CENTROCUSTO_ID
 * @property string|null $CREATED_AT
 * @property int $FINANCEIRO_ID
 * @property int $ID
 * @property float|null $PERCENTUAL
 * @property int $PLANOCONTA_ID
 * @property string|null $UPDATED_AT
 * @property float $VALOR
 * @property-read \App\Centrocusto $centroCusto
 * @property-read \App\Financeiro $financeiro
 * @property-read \App\Planoconta $planoConta
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeirorateio whereCENTROCUSTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeirorateio whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeirorateio whereFINANCEIROID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeirorateio whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeirorateio wherePERCENTUAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeirorateio wherePLANOCONTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeirorateio whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeirorateio whereVALOR($value)
 * @mixin \Eloquent
 */
class Financeirorateio extends Model
{

    protected $fillable = ['financeiro_id', 'planoconta_id', 'centrocusto_id',
        'valor', 'percentual'];

    public function financeiro()
    {
        return $this->belongsTo('App\Financeiro');
    }

    public function planoConta()
    {
        return $this->belongsTo('App\Planoconta', 'planoconta_id');
    }

    public function centroCusto()
    {
        return $this->belongsTo('App\Centrocusto', 'centrocusto_id');
    }

}
