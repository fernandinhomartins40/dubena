<?php

namespace App\Models\Pedido;

use App\Domain\Pedido\EfeitoPedido;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoSituacaoHistorico extends Model
{
    use HasFactory;

    protected $table = 'pedidosituacaohistorico';

    protected $fillable = ['pedido_id', 'pedidosituacao_id', 'user_id', 'efeito', 'datahora'];

    protected function casts(): array
    {
        return ['efeito' => EfeitoPedido::class, 'datahora' => 'datetime'];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }
}
