<?php

namespace App\Monitora\Models;
use Illuminate\Database\Eloquent\Model;

class Config extends MonitoraModel
{
    protected $fillable = ['urlsistemaweb', 'urltraccar', 'usertraccar', 'passwordtraccar', 'keygooglemaps', 'temporefresh'];

}
