<?php

namespace App\Models\Caixa;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimento de conta (o log que torna o saldo AUDITÁVEL). valor ASSINADO.
 */
class ContaMovimento extends Model
{
    use HasFactory;

    protected $table = 'contamovimentos';

    protected $fillable = [
        'empresa_id', 'conta_id', 'contamovimentotipo_id', 'financeiroparcela_id',
        'tipo', 'pagarreceber', 'valor', 'saldo_resultante', 'juros', 'multa', 'desconto',
        'descricao', 'origem', 'origem_id', 'estorno_de_id', 'user_id', 'datahora',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'saldo_resultante' => 'decimal:2',
            'juros' => 'decimal:2',
            'multa' => 'decimal:2',
            'desconto' => 'decimal:2',
            'datahora' => 'datetime',
        ];
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(Conta::class);
    }
}
