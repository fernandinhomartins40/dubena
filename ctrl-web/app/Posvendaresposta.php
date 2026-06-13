<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Posvendaresposta
 *
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $ID
 * @property int $POSVENDAPERGUNTA_ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Posvendapergunta $posVendaPergunta
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvendaresposta whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvendaresposta whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvendaresposta whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvendaresposta wherePOSVENDAPERGUNTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvendaresposta whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Posvendaresposta extends Model
{

    protected $fillable = ['descricao', 'posvendapergunta_id'];

    public function posVendaPergunta()
    {
        return $this->belongsTo('App\Posvendapergunta');
    }
    
}
