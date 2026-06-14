<?php

namespace App\Monitora\Models;
use Illuminate\Database\Eloquent\Model;

class Device extends MonitoraModel
{
  protected $fillable = ['uniqueid', 'name', 'description', 'veiculo_id'];

  public function veiculo()
  {
      return $this->belongsTo('App\Veiculo');
  }
}
