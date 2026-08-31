<?php

namespace App\Models\Financeiro;

use App\Domain\Shared\Auditavel;
use App\Domain\Tenant\BelongsToTenant;
use App\Models\Caixa\Conta;
use App\Models\Caixa\ContaMovimento;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F5-04 — um lançamento do extrato bancário, e o par que ele formou (ou não).
 *
 * A linha guarda **os dois lados**: o do banco, congelado como veio no OFX, e o
 * do ERP, que pode estar ausente enquanto o lançamento está pendente.
 *
 * Auditável porque a decisão de casar ou desfazer um par é decisão sobre
 * dinheiro: alguém afirma que aquele débito no extrato é aquela saída no
 * sistema. Quando o valor não bate no fim do mês, é essa afirmação que se vai
 * querer revisar.
 */
class ConciliacaoLancamento extends Model
{
    use Auditavel;
    use BelongsToTenant;

    protected $table = 'conciliacao_lancamentos';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'conta_id', 'fitid',
        'data_banco', 'valor_banco', 'descricao_banco', 'tipo_banco',
        'conta_movimento_id', 'origem_match', 'tolerancia_dias',
        'decidido_por', 'decidido_em', 'motivo',
    ];

    protected function casts(): array
    {
        return [
            'data_banco' => 'date',
            'valor_banco' => 'decimal:2',
            'tolerancia_dias' => 'integer',
            'decidido_em' => 'datetime',
        ];
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(Conta::class, 'conta_id');
    }

    public function movimento(): BelongsTo
    {
        return $this->belongsTo(ContaMovimento::class, 'conta_movimento_id');
    }

    public function decisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decidido_por');
    }

    public function conciliado(): bool
    {
        return $this->conta_movimento_id !== null
            && in_array($this->origem_match, [OrigemMatch::AUTOMATICO->value, OrigemMatch::MANUAL->value], true);
    }
}
