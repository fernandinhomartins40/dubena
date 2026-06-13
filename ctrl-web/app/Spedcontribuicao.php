<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Spedcontribuicao
 *
 * @property string|null $CREATED_AT
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedcontribuicao whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedcontribuicao whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Spedcontribuicao whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Spedcontribuicao extends Model
{
    protected $fillable = ['grupo_id', 'empresa_id'];
}
