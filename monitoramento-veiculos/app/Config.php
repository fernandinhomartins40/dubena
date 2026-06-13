<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Config extends Model
{
    protected $fillable = ['urlsistemaweb', 'urltraccar', 'usertraccar', 'passwordtraccar', 'keygooglemaps', 'temporefresh'];

}
