<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Chequerecebidofinanceiro
 *
 * @property int $CHEQUERECEBIDO_ID
 * @property string|null $CREATED_AT
 * @property int $FINANCEIRO_ID
 * @property int $FINANCEIROPARCELA_ID
 * @property int $ID
 * @property int $NUMEROCHEQUE
 * @property string|null $UPDATED_AT
 * @property-read \App\Chequerecebido $chequeRecebido
 * @property-read \App\Financeiro $financeiro
 * @property-read \App\Financeiroparcela $financeiroParcela
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebidofinanceiro whereCHEQUERECEBIDOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebidofinanceiro whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebidofinanceiro whereFINANCEIROID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebidofinanceiro whereFINANCEIROPARCELAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebidofinanceiro whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebidofinanceiro whereNUMEROCHEQUE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebidofinanceiro whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Chequerecebidofinanceiro extends Model
{

    protected $fillable = ['chequerecebido_id', 'financeiro_id', 'financeiroparcela_id', 
        'numerocheque'];

    public function chequeRecebido()
    {
        return $this->belongsTo('App\Chequerecebido');
    }

    public function financeiro()
    {
        return $this->belongsTo('App\Financeiro');
    }

    public function financeiroParcela()
    {
        return $this->belongsTo('App\Financeiroparcela', 'financeiroparcela_id');
    }

}
