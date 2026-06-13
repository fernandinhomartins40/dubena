<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Promotorvenda extends Model
{
    use \App\Services\RevisionsTraitService;

    protected $identity = "empresa_id";

    protected $revisionEnabled = true;

    protected $fillable = [
        "empresa_id", "user_id", "cliente_id", "ausente", "uf",
        "cidade_id", "bairro_id", "setor_id", "rua_id", "numero",
        "complemento", "ponto_referencia"
    ];

    public function promotor()
    {
        return $this->belongsTo("App\User");
    }

    public function cliente()
    {
        return $this->belongsTo("App\Cliente");
    }

    public function uf()
    {
        return $this->belongsTo('App\Estado', 'uf');
    }

    public function cidade()
    {
        return $this->belongsTo('App\Cidade');
    }

    public function bairro()
    {
        return $this->belongsTo('App\Bairro');
    }

    public function rua()
    {
        return $this->belongsTo('App\Rua');
    }
}
