<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Inventario
 *
 * @property string|null $CREATED_AT
 * @property string|null $DATAINVENTARIO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $MESENTREGA
 * @property string|null $UPDATED_AT
 * @property float|null $VALORINVENTARIO
 * @property-read \App\Empresa $empresa
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Inventarioitems[] $items
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Inventario whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Inventario whereDATAINVENTARIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Inventario whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Inventario whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Inventario whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Inventario whereMESENTREGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Inventario whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Inventario whereVALORINVENTARIO($value)
 * @mixin \Eloquent
 */
class Inventario extends Model
{

    protected $fillable = ["grupo_id","empresa_id","mesentrega","datainventario","valorinventario","created_at","updated_at"];

    public function empresa()
    {
        return $this->hasOne('App\Empresa');
    }

    public function items()
    {
        return $this->hasMany('App\Inventarioitems','inventario_id');
    }

}
