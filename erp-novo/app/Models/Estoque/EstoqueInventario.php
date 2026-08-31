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

    protected $fillable = [
        'empresa_id', 'setor_id', 'data', 'situacao', 'observacao',
        // F4-03: quem CONTOU e quem APROVOU o ajuste — costumam ser pessoas
        // diferentes (o conferente vai ao depósito, o supervisor autoriza), e o
        // ator do movimento de acerto não distingue as duas.
        'contado_por', 'aprovado_por', 'aprovado_em',
    ];

    protected function casts(): array
    {
        return ['data' => 'date', 'aprovado_em' => 'datetime'];
    }

    public function itens(): HasMany
    {
        return $this->hasMany(EstoqueInventarioItem::class);
    }
}
