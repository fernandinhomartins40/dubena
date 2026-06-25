<?php

namespace App\Models\Caixa;

use App\Domain\Shared\Auditavel;
use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conta (caixa/banco/cartão) — escopo por empresa. saldo_atual é derivável de
 * Σ contamovimentos.valor (invariante de cutover/teste).
 */
class Conta extends Model
{
    use Auditavel;
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'contas';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'descricao', 'tipo', 'banco_id', 'agencia', 'numero',
        'saldo_inicial', 'saldo_atual', 'fechado', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'saldo_inicial' => 'decimal:2',
            'saldo_atual' => 'decimal:2',
            'fechado' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    public function movimentos(): HasMany
    {
        return $this->hasMany(ContaMovimento::class);
    }
}
