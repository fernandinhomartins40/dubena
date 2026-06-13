<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Contareabertura
 *
 * @property int $CONTA_ID
 * @property string|null $CREATED_AT
 * @property string $DATAHORAREABERTA
 * @property int $ID
 * @property string $MOTIVO
 * @property string|null $UPDATED_AT
 * @property int $USER_ID
 * @property-read \App\Conta $conta
 * @property-read \App\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contareabertura whereCONTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contareabertura whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contareabertura whereDATAHORAREABERTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contareabertura whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contareabertura whereMOTIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contareabertura whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contareabertura whereUSERID($value)
 * @mixin \Eloquent
 */
class Contareabertura extends Model
{

    protected $fillable = ['conta_id', 'user_id', 'datahorareaberta', 'motivo'];

    public function conta()
    {
        return $this->belongsTo('App\Conta');
    }

    public function user()
    {
        return $this->belongsTo('App\User');
    }

}
