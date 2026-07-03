<?php

namespace App\Models\Caixa;

use App\Domain\Shared\Auditavel;
use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimento de conta (o log que torna o saldo AUDITÁVEL). valor ASSINADO.
 *
 * Escopado por empresa (F00.5): tem coluna `empresa_id`, então o global scope de
 * tenant evita estornar/ler movimento de OUTRA empresa por id (IDOR apontado na
 * auditoria). Operações internas que precisam ignorar o escopo usam withoutTenant().
 *
 * Auditável (S-4): cada movimento de caixa entra em audit_logs (quem/quando/IP +
 * valores) — o dinheiro é o dado mais sensível e precisa de trilha completa.
 */
class ContaMovimento extends Model
{
    use Auditavel;
    use BelongsToTenant;
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
