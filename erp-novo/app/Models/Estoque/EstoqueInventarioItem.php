<?php

namespace App\Models\Estoque;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Item contado de um inventário — C11. */
class EstoqueInventarioItem extends Model
{
    use BelongsToTenant;

    /** @var array<string,string> FK => tabela do pai (herança de empresa_id na criação sem tenant ativo). */
    protected $tenantParent = ['estoque_inventario_id' => 'estoque_inventarios'];

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
