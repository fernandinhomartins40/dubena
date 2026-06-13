<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Pixtransaction extends Model
{

    protected $fillable = [
        "pedido_id", "txid", "loc_id", "loc_tipo",
        "loc_criacao", "location", "status", "valor",
        "correlation_id", "pixcopiaecola", "expires_at",
        "revisao", "endtoendid", "valorpago", "datapagamento",
        "cobranca_id", "pixpedido_id"
    ];

    protected $dates = [
        "expires_at",
        "loc_criacao"
    ];

    function pedido()
    {
        return $this->belongsTo("App\Pedido");
    }

    function pixpedido()
    {
        return $this->belongsTo("App\Pixpedido");
    }
}
