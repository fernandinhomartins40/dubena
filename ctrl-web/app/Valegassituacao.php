<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Valegassituacao
 *
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Valegas[] $valeGas
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegassituacao whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegassituacao whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegassituacao whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegassituacao whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Valegassituacao extends Model
{


    protected $fillable = ['descricao'];

    public function valeGas()
    {
        return $this->hasMany('App\Valegas');
    }

}
