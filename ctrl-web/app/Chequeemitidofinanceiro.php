<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Chequeemitidofinanceiro
 *
 * @property int $CHEQUEEMITIDO_ID
 * @property string|null $CREATED_AT
 * @property int $FINANCEIRO_ID
 * @property int $FINANCEIROPARCELA_ID
 * @property int $ID
 * @property int $NUMEROCHEQUE
 * @property string|null $UPDATED_AT
 * @property-read \App\Chequeemitido $chequeEmitido
 * @property-read \App\Financeiro $financeiro
 * @property-read \App\Financeiroparcela $financeiroParcela
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitidofinanceiro whereCHEQUEEMITIDOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitidofinanceiro whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitidofinanceiro whereFINANCEIROID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitidofinanceiro whereFINANCEIROPARCELAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitidofinanceiro whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitidofinanceiro whereNUMEROCHEQUE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitidofinanceiro whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Chequeemitidofinanceiro extends Model
{
    
    protected $fillable = ['chequeemitido_id', 'financeiro_id', 'financeiroparcela_id', 'numerocheque'];

    public function chequeEmitido()
    {
        return $this->belongsTo('App\Chequeemitido');
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
