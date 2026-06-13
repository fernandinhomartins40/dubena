<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Sorteio extends Model
{
    protected $fillable = ["grupo_id", "empresa_id", "datainicio", "datafim", "datasorteio", "app", "pedido_id", "cliente_id"];

    public function grupo()
    {
        return $this->belongsTo("App\Grupo");
    }

    public function empresa()
    {
        return $this->belongsTo("App\Empresa");
    }

    public function pedido()
    {
        return $this->belongsTo("App\Pedido");
    }

    public function cliente()
    {
        return $this->belongsTo("App\Cliente");
    }
}
