<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Spedfiscal
 *
 * @property string|null $CREATED_AT
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedfiscal whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedfiscal whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedfiscal whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Spedfiscal extends Model
{
    protected $fillable = ['grupo_id', 'empresa_id'];
}
