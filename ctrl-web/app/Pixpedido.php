<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Pixpedido extends Model
{
   protected $fillable = ["grupo_id", "empresa_id", "clienteapi_id", "pedidoapi_id", "valorvenda", "json_data"];


   public function empresasGrupo()
   {
      return $this->belongsTo('App\EmpresasGrupo');
   }

   public function empresa()
   {
      return $this->belongsTo('App\Empresa');
   }
}
