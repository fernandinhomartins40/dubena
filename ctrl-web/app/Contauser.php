<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Contauser
 *
 * @property int $CONTA_ID
 * @property string|null $CREATED_AT
 * @property string|null $ESTORNAR
 * @property int $ID
 * @property string|null $LANCARFECHADO
 * @property string $OPERAR
 * @property string $TRANSFERIR
 * @property string|null $UPDATED_AT
 * @property int $USER_ID
 * @property string $VISUALIZAR
 * @property-read \App\Conta $conta
 * @property-read \App\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contauser whereCONTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contauser whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contauser whereESTORNAR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contauser whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contauser whereLANCARFECHADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contauser whereOPERAR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contauser whereTRANSFERIR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contauser whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contauser whereUSERID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contauser whereVISUALIZAR($value)
 * @mixin \Eloquent
 */
class Contauser extends Model
{
    protected $fillable = ['conta_id', 'user_id', 'operar', 'visualizar', 'transferir', 'estornar', 'lancarfechado'];

    public function conta()
    {
        return $this->belongsTo('App\Conta');
    }

    public function user()
    {
        return $this->belongsTo('App\User');
    }

}
