<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Veiculo extends Model
{
    protected $fillable = [
        'grupo_id',
        'empresa_id',
        'descricao',
        'veiculotipo_id',
        'placa',
        'ativo',
        'descricao',
        'km_atual',
        'motorista',
        'unique_id',
        'deviceid',
        'veiculoerp_id'
    ];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function veiculotipo()
    {
        return $this->belongsTo('App\Veiculotipo');
    }
}
