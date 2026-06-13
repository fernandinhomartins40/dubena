<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Logsenha extends Model
{

    protected $fillable = ["grupo_id", "empresa_id", "user_id", "datahora", "rota", "motivo", "status"];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function user()
    {
        return $this->belongsTo('App\User');
    }
}
