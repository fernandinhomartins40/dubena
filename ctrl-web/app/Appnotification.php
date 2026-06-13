<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Appnotification extends Model
{

    protected $fillable = [
        "grupo_id",
        "empresa_id",
        "fcmtitle",
        "fcmbody",
        "instant",
        "status",
        "islayout",
        "imagem"
    ];

    public function empresa()
    {
        return $this->belongsTo("App\Empresa");
    }
}
