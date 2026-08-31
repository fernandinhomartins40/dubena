<?php

namespace App\Models\Estoque;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\Produto\Produto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimento de estoque (o log que torna o saldo AUDITÁVEL). Quantidade ASSINADA
 * (+entrada / -saída); `saldo_resultante` = saldo logo após o movimento.
 */
class EstoqueHistorico extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'estoquehistorico';

    protected $fillable = [
        'empresa_id', 'tenant_account_id', 'setor_id', 'produto_id', 'tipo', 'quantidade',
        'custo_unitario', 'saldo_resultante', 'origem', 'origem_id',
        // F4-01: quando informada, o mesmo movimento nunca e gravado duas vezes.
        'chave_idempotencia',
        'user_id',
    ];

    /** Custo só pode sair por um presenter que aplique field-level. */
    protected $hidden = ['custo_unitario'];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'custo_unitario' => 'decimal:4',
            'saldo_resultante' => 'decimal:3',
        ];
    }

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
