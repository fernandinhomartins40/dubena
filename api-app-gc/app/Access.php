<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Access extends Model
{

    protected $fillable = ["cliente_id", "ip"];

    public function cliente()
    {
        return $this->belongsTo(ClienteImportacao::class, "cliente_id");
    }

}
