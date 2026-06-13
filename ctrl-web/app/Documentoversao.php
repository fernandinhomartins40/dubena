<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Documentoversao extends Model
{
    protected $fillable = ['descricao', 'numeroversao', 'documento_id', 'dataemissao', 'datavencimento', 'nomearquivo', 'ativo'];

    public function documento()
    {
        return $this->belongsTo('App\Documento');
    }
}
