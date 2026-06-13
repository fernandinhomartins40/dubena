<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Clientepromocao
 *
 * @property int $CLIENTE_ID
 * @property string|null $CREATED_AT
 * @property string $DATAFIM
 * @property string $DATAINICIO
 * @property int $ID
 * @property int $MEDIADIAS
 * @property int $PROMOCAO_ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Cliente $cliente
 * @property-read \App\Promocao $promocao
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientepromocao whereCLIENTEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientepromocao whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientepromocao whereDATAFIM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientepromocao whereDATAINICIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientepromocao whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientepromocao whereMEDIADIAS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientepromocao wherePROMOCAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientepromocao whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Clientepromocao extends Model
{
    protected $fillable = [
      'cliente_id',
      'datainicio',
      'datafim',
      'mediadias',
      'promocao_id'
    ];

    
    public function cliente()
    {
        return $this->belongsTo('App\Cliente');
    }
    public function promocao(){
      return $this->belongsTo('App\Promocao');
    }
}
