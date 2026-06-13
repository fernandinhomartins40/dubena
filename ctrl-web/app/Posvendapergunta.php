<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Posvendapergunta
 *
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $ID
 * @property int $POSVENDA_ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Posvenda $posVenda
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Posvendaresposta[] $posvendarespostas
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvendapergunta whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvendapergunta whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvendapergunta whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvendapergunta wherePOSVENDAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvendapergunta whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Posvendapergunta extends Model
{

    protected $fillable = ['posvenda_id', 'descricao'];

    public function posvendarespostas()
    {
        return $this->hasMany('App\Posvendaresposta');
    }

    public function posVenda()
    {
        return $this->belongsTo('App\Posvenda');
    }

}
