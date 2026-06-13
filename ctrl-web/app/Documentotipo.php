<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Documentotipo extends Model
{
    protected $fillable = ['descricao', 'grupo_id', 'diasalerta'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function documentos()
    {
        return $this->hasMany('App\Documento');
    }
}
