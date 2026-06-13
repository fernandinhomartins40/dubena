<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Empresabemdepreciacao
 *
 * @property string|null $CREATED_AT
 * @property string|null $DEPRECIACAODATA
 * @property float $DEPRECIACAOVALOR
 * @property int $EMPRESABEM_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Empresabem $empresaBem
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresabemdepreciacao whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresabemdepreciacao whereDEPRECIACAODATA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresabemdepreciacao whereDEPRECIACAOVALOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresabemdepreciacao whereEMPRESABEMID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresabemdepreciacao whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresabemdepreciacao whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Empresabemdepreciacao extends Model
{

    protected $fillable = ['empresabem_id', 'depreciacaodata', 'depreciacaovalor'];

    public function empresaBem()
    {
        return $this->belongsTo('App\Empresabem');
    }

}
