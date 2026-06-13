<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

class Access extends ApiModel
{

    protected $fillable = ["cliente_id", "ip"];

    public function cliente()
    {
        return $this->belongsTo(ClienteImportacao::class, "cliente_id");
    }

}


