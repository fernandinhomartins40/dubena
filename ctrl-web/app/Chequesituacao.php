<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Chequesituacao
 *
 * @property string|null $CHEQUEEMITIDO
 * @property string|null $CHEQUERECEBIDO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Chequeemitido[] $chequeEmitido
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Chequerecebido[] $chequeRecebido
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequesituacao whereCHEQUEEMITIDO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequesituacao whereCHEQUERECEBIDO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequesituacao whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequesituacao whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequesituacao whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequesituacao whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Chequesituacao extends Model
{


    protected $fillable = ['descricao', 'chequerecebido', 'chequeemitido'];

    public function chequeEmitido()
    {
        return $this->hasMany('App\Chequeemitido');
    }

    public function chequeRecebido()
    {
        return $this->hasMany('App\Chequerecebido');
    }

}
