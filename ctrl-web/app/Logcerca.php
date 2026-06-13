<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Logcerca extends Model
{
    protected $fillable = [
        "empresa_id",
        "grupo_id",
        "setor_id",
        "colaborador_id",
        "veiculo_id",
        "datahora",
        "cerca",
        "cerca_id",
        "placa",
        "veiculo",
        "motorista",
        "latitude",
        "longitude",
        "tipo",
    ];

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

    public function colaborador()
    {
        return $this->belongsTo('App\Colaborador');
    }

    public function veiculo()
    {
        return $this->belongsTo('App\Veiculo');
    }
}
