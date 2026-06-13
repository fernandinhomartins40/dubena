<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Setor extends Model
{
    protected $fillable = ['grupo_id', 'empresa_id', 'descricao', 'latitude',
        'longitude', 'ativo', 'rua', 'numero',
        'cep', 'bairro', 'cidade', 'uf'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }
    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }
}
