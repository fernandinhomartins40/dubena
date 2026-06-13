<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EmpresasGrupo extends Model
{

    protected $fillable = ['descricao', 'ativo', 'logo'];

    public function empresas()
    {
        return $this->hasMany('App\Empresa');
    }

}
