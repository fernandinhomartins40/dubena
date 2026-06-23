<?php

namespace App\Models\Gestao;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Item de cupom fiscal — C11. */
class CupomFiscalItem extends Model
{
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
