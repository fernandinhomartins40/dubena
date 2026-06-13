<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Nfoperacaoprodutoconvenio extends Model
{
    protected $fillable = ['nfoperacao_id', 'produto_id',
        'nfoperacaoconvenio_id'];

    public function nfoperacao()
    {
        return $this->belongsTo('App\Nfoperacao');
    }

    public function produto()
    {
        return $this->belongsTo('App\Produto');
    }

    public function nfoperacaoconvenio()
    {
        return $this->belongsTo('App\Nfoperacao', 'nfoperacaoconvenio_id');
    }
}
