<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Contatalao
 *
 * @property int $CHEQUENUMATUAL
 * @property int $CHEQUENUMFINAL
 * @property int $CHEQUENUMINICIAL
 * @property int $CONTA_ID
 * @property string|null $CREATED_AT
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Chequeemitido[] $chequeEmitido
 * @property-read \App\Conta $conta
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contatalao whereCHEQUENUMATUAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contatalao whereCHEQUENUMFINAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contatalao whereCHEQUENUMINICIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contatalao whereCONTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contatalao whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contatalao whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contatalao whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Contatalao extends Model
{


    protected $fillable = ['conta_id', 'chequenuminicial', 'chequenumfinal', 'chequenumatual'];

    public function conta()
    {
        return $this->belongsTo('App\Conta');
    }

    public function chequeEmitido()
    {
        return $this->hasMany('App\Chequeemitido');
    }

}
