<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Appvideo extends Model
{
    protected $fillable = ['grupo_id', 'empresa_id', 'titulo', 'caminho', 'mensagem', 'status', 'ativo'];

    public function empresa()
    {
        return $this->belongsTo("App\Empresa");
    }
}
