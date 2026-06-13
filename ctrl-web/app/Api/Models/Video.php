<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class Video extends ApiModel
{
    protected $fillable = ["user_id", "url", "titulo", "ativo"];

    public function user()
    {
        return $this->belongsTo(User::class, "user_id");
    }
}


