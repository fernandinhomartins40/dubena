<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Chequerecebidotransferencia
 *
 * @property int $CHEQUERECEBIDO_ID
 * @property int $CONTATRANSFERENCIA_ID
 * @property string|null $CREATED_AT
 * @property int $ID
 * @property string $TIPOTRANSFERENCIA
 * @property string|null $UPDATED_AT
 * @property-read \App\Chequerecebido $chequeRecebido
 * @property-read \App\Contatransferencia $contaTranferencia
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebidotransferencia whereCHEQUERECEBIDOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebidotransferencia whereCONTATRANSFERENCIAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebidotransferencia whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebidotransferencia whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebidotransferencia whereTIPOTRANSFERENCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebidotransferencia whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Chequerecebidotransferencia extends Model
{
    protected $fillable = ['contatransferencia_id', 'chequerecebido_id', 'tipotransferencia'];


    public function chequeRecebido()
    {
        return $this->belongsTo('App\Chequerecebido', 'chequerecebido_id');
    }


    public function contaTranferencia()
    {
        return $this->belongsTo('App\Contatransferencia', 'contatransferencia_id');
    }
}
