<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\UserPolylines
 *
 * @property int $id
 * @property int $user_id
 * @property float|null $latitude
 * @property float|null $longitude
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|\App\UserPolylines whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\UserPolylines whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\UserPolylines whereLatitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\UserPolylines whereLongitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\UserPolylines whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\UserPolylines whereUserId($value)
 * @mixin \Eloquent
 */
class UserPolylines extends Model
{
    protected $table = "userpolylines";

    protected $fillable = [
        "user_id", "latitude", "longitude"
    ];
}
