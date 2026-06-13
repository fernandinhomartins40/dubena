<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\GeneralConfig
 *
 * @property int $id
 * @property string $keygooglemaps
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|\App\GeneralConfig whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\GeneralConfig whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\GeneralConfig whereKeygooglemaps($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\GeneralConfig whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GeneralConfig extends ApiModel
{
    protected $table = 'generalconfigs';

    protected $fillable = [
        'keygooglemaps'
    ];
}


