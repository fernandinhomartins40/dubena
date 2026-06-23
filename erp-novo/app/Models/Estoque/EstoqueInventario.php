<?php

namespace App\Models\Estoque;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Inventário (contagem de estoque) — C11. Escopo por empresa. */
class EstoqueInventario extends Model
{
    use BelongsToTenant;

    protected $table = 'estoque_inventarios';

    protected $fillable = ['empresa_id', 'setor_id', 'data', 'situacao', 'observacao'];

    protected function casts(): array
    {
        return ['data' => 'date'];
    }

    public function itens(): HasMany
    {
        return $this->hasMany(EstoqueInventarioItem::class);
    }
}
