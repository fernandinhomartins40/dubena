<?php

namespace App\Models\Gestao;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Item de cupom fiscal — C11. */
class CupomFiscalItem extends Model
{
    use BelongsToTenant;

    /** @var array<string,string> FK => tabela do pai (herança de empresa_id na criação sem tenant ativo). */
    protected $tenantParent = ['cupom_fiscal_id' => 'cupons_fiscais'];

    protected $table = 'cupom_fiscal_itens';

    protected $fillable = ['cupom_fiscal_id', 'produto_id', 'quantidade', 'valor_unitario', 'valor_total'];

    protected function casts(): array
    {
        return ['quantidade' => 'decimal:4', 'valor_unitario' => 'decimal:4', 'valor_total' => 'decimal:2'];
    }

    public function cupom(): BelongsTo
    {
        return $this->belongsTo(CupomFiscal::class);
    }
}
