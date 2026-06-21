<?php

namespace App\Models\Pedido;

use App\Models\Produto\Produto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoItem extends Model
{
    use HasFactory;

    protected $table = 'pedidoitens';

    protected $fillable = ['pedido_id', 'produto_id', 'quantidade', 'preco_unitario', 'desconto', 'valor_total'];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'preco_unitario' => 'decimal:2',
            'desconto' => 'decimal:2',
            'valor_total' => 'decimal:2',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
