<?php

namespace App\Models\Fiscal;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\Produto\Produto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item do inventário fiscal: quantidade e valor unitário DECLARADOS.
 *
 * `descricao_snapshot` congela o nome do produto: o documento entregue ao fisco
 * não muda porque o cadastro foi renomeado depois (F3-03).
 */
class InventarioFiscalItem extends Model
{
    use BelongsToTenant;

    protected $table = 'inventario_fiscal_itens';

    protected $fillable = [
        'empresa_id', 'tenant_account_id', 'inventario_fiscal_id', 'produto_id',
        'descricao_snapshot', 'quantidade', 'valor_unitario',
    ];

    /** Sem envelope (ETL/seed), empresa e tenant vêm do inventário pai. */
    protected $tenantParent = ['inventario_fiscal_id' => 'inventarios_fiscais'];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'valor_unitario' => 'decimal:4',
        ];
    }

    public function inventario(): BelongsTo
    {
        return $this->belongsTo(InventarioFiscal::class, 'inventario_fiscal_id');
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
