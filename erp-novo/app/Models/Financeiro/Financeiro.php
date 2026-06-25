<?php

namespace App\Models\Financeiro;

use App\Domain\Shared\Auditavel;
use App\Domain\Financeiro\AgrupamentoStatus;
use App\Domain\Tenant\BelongsToTenant;
use App\Models\Cliente\Cliente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Título financeiro (a pagar/receber) — escopo por empresa. Valor decimal nativo;
 * agrupamento_status como enum. Σ parcelas.valor = valor (invariante).
 */
class Financeiro extends Model
{
    use Auditavel;
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'financeiros';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'cliente_id', 'planoconta_id', 'centrocusto_id',
        'pagarreceber', 'documento', 'descricao', 'valor', 'data_emissao',
        'agrupamento_status', 'agrupador_id', 'origem', 'origem_id', 'cancelado',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'data_emissao' => 'date',
            'agrupamento_status' => AgrupamentoStatus::class,
            'cancelado' => 'boolean',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function parcelas(): HasMany
    {
        return $this->hasMany(FinanceiroParcela::class);
    }

    public function rateios(): HasMany
    {
        return $this->hasMany(FinanceiroRateio::class);
    }
}
