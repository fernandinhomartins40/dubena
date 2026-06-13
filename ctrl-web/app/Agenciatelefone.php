<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Agenciatelefone
 *
 * @property int $AGENCIA_ID
 * @property string|null $CREATED_AT
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string $TELEFONE
 * @property int $TELEFONETIPO_ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Agencia $colaborador
 * @property-read \App\Telefonetipo $telefonetipo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agenciatelefone whereAGENCIAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agenciatelefone whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agenciatelefone whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agenciatelefone whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agenciatelefone whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agenciatelefone whereTELEFONE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agenciatelefone whereTELEFONETIPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agenciatelefone whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Agenciatelefone extends Model
{
  protected $fillable = ['grupo_id', 'empresa_id', 'agencia_id', 'telefone', 'telefonetipo_id' ];

  public function colaborador()
  {
      return $this->belongsTo('App\Agencia');
  }

  public function telefonetipo()
  {
      return $this->belongsTo('App\Telefonetipo');
  }
}
