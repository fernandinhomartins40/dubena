<?php

namespace App\Monitora\Models;
use Illuminate\Database\Eloquent\Model;

class Ultimaposicao extends MonitoraModel
{
  protected $fillable = ['grupo_id', 'empresa_id', 'datahora', 'veiculo_id',
      'latitude', 'longitude', 'altitude', 'azimute', 'velocidade',
      'velocidade_anterior', 'distancia', 'deviceid'];

  public function empresasGrupo()
  {
      return $this->belongsTo('App\EmpresasGrupo');
  }
  public function empresa()
  {
      return $this->belongsTo('App\Empresa');
  }
  public function veiculo()
  {
      return $this->belongsTo('App\Veiculo');
  }
}
