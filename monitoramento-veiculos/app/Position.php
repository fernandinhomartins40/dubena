<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
   protected $fillable = ['latitude', 'longitude', 'altitude',
        'course', 'speed', 'deviceid', 'dhposition',
        'address', 'power'];

}
