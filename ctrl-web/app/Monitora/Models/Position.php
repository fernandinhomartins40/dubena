<?php

namespace App\Monitora\Models;
use Illuminate\Database\Eloquent\Model;

class Position extends MonitoraModel
{
   protected $fillable = ['latitude', 'longitude', 'altitude',
        'course', 'speed', 'deviceid', 'dhposition',
        'address', 'power'];

}
