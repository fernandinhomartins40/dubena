<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Posvendapesquisaresposta
 *
 * @property string|null $CREATED_AT
 * @property int $ID
 * @property int $POSVENDAPESQUISA_ID
 * @property int $POSVENDARESPOSTA_ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Posvendapesquisa $posVendaPesquisa
 * @property-read \App\Posvendaresposta $posVendaReposta
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvendapesquisaresposta whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvendapesquisaresposta whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvendapesquisaresposta wherePOSVENDAPESQUISAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvendapesquisaresposta wherePOSVENDARESPOSTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvendapesquisaresposta whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Posvendapesquisaresposta extends Model
{

    protected $fillable = ['posvendapesquisa_id', 'posvendaresposta_id'];

    public function posVendaPesquisa()
    {
        return $this->belongsTo('App\Posvendapesquisa');
    }

    public function posVendaReposta()
    {
        return $this->belongsTo('App\Posvendaresposta');
    }

}
