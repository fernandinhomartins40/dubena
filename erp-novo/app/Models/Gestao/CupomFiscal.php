<?php

namespace App\Models\Gestao;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Cupom fiscal SAT/CFe — C11. Escopo por empresa. Emissão real = gate regional. */
class CupomFiscal extends Model
{
    use BelongsToTenant;

    protected $table = 'cupons_fiscais';

    protected $fillable = ['empresa_id', 'pedido_id', 'numero', 'chave', 'valor_total', 'situacao', 'emitido_em'];

    protected function casts(): array
    {
        return ['valor_total' => 'decimal:2', 'emitido_em' => 'datetime'];
    }

    public function itens(): HasMany
    {
        return $this->hasMany(CupomFiscalItem::class);
    }
}
