<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Nfcst extends Model
{
    protected $fillable = ['grupo_id', 'empresa_id', 'codigo', 'descricao'];

    public function empresasGrupos()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }
}
