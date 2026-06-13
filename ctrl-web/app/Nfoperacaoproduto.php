<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Nfoperacaoproduto extends Model
{

    protected $fillable = ['nfoperacao_id', 'produto_id',
        'nfoperacaoapp_id'];

    public function nfoperacao()
    {
        return $this->belongsTo('App\Nfoperacao');
    }

    public function produto()
    {
        return $this->belongsTo('App\Produto');
    }

    public function nfoperacaoapp()
    {
        return $this->belongsTo('App\Nfoperacao', 'nfoperacaoapp_id');
    }
}
