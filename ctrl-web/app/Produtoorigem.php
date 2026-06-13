<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Produtoorigem extends Model
{

    protected $fillable = ["produto_id", "indimport", "cuforig", "porig"];

    public function produto()
    {
        return $this->belongsTo('App\Produto');
    }

    public function uf()
    {
        return $this->belongsTo("App\Estado", "cuforig", "cod_ibge");
    }
}
