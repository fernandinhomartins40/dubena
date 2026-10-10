<?php

namespace App\Models\Fiscal;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Inventário fiscal — o estoque declarado ao fisco (SPED Fiscal, Bloco H).
 *
 * Não confundir com `EstoqueInventario` (contagem física): aquele é por setor
 * e, ao efetivar, ajusta o saldo. Este é por empresa, tem valor e não mexe em
 * saldo nenhum — é um documento.
 */
class InventarioFiscal extends Model
{
    use BelongsToTenant;

    protected $table = 'inventarios_fiscais';

    protected $fillable = [
        'empresa_id', 'tenant_account_id', 'mes_entrega', 'data_inventario',
        'motivo', 'valor_total', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'mes_entrega' => 'date',
            'data_inventario' => 'date',
            'valor_total' => 'decimal:2',
        ];
    }

    public function itens(): HasMany
    {
        return $this->hasMany(InventarioFiscalItem::class);
    }
}
