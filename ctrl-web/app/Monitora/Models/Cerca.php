<?php

namespace App\Monitora\Models;
use Illuminate\Database\Eloquent\Model;

class Cerca extends Model
{
    protected $fillable = ['grupo_id', 'empresa_id', 'descricao', 'setor_id',
        'cor', 'ativo'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }
    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }
    public function setor()
    {
        return $this->belongsTo('App\Setor');
    }
    public function coordenadas()
    {
        return $this->hasMany('App\Cercapoligono');
    }
}
