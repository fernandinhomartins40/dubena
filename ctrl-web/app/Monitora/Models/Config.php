<?php

namespace App\Monitora\Models;
use Illuminate\Database\Eloquent\Model;

class Config extends Model
{
    protected $fillable = ['urlsistemaweb', 'urltraccar', 'usertraccar', 'passwordtraccar', 'keygooglemaps', 'temporefresh'];

}
