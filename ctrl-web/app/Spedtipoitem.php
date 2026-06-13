<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Spedtipoitem
 *
 * @property string $CODIGO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedtipoitem whereCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedtipoitem whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedtipoitem whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedtipoitem whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedtipoitem whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Spedtipoitem extends Model
{
    protected $fillable = ['codigo','descricao'];
}
