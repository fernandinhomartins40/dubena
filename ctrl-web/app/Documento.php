<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Documento extends Model
{
    protected $fillable = ['descricao', 'grupo_id', 'empresa_id', 'colaborador_id', 'documentotipo_id'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }
    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }
    public function documentotipo()
    {
        return $this->belongsTo('App\Documentotipo');
    }
    public function colaborador()
    {
        return $this->belongsTo('App\Colaborador');
    }
    public function versoes()
    {
        return $this->hasMany('App\Documentoversao')->orderBy('numeroversao');
    }
    public function versoesAtivas()
    {
        return $this->hasMany('App\Documentoversao')->where('ativo', true)->orderBy('numeroversao');
    }
}
