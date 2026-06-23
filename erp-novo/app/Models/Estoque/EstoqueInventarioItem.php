<?php

namespace App\Models\Estoque;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Item contado de um inventário — C11. */
class EstoqueInventarioItem extends Model
{
    protected $table = 'estoque_inventario_itens';

    protected $fillable = [
        'estoque_inventario_id', 'produto_id', 'quantidade_contada', 'quantidade_sistema',
    ];

    protected function casts(): array
    {
        return ['quantidade_contada' => 'decimal:4', 'quantidade_sistema' => 'decimal:4'];
    }

    public function inventario(): BelongsTo
    {
        return $this->belongsTo(EstoqueInventario::class, 'estoque_inventario_id');
    }
}
