<?php

namespace App\Models\Satelite;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma linha do extrato do comodato: empréstimo, devolução ou estorno.
 *
 * Sem trilha de auditoria própria (`Auditavel`) de propósito — o movimento JÁ É
 * o registro histórico. Ele nunca é alterado: corrigir uma devolução errada se
 * faz lançando um ESTORNO, não editando a linha.
 */
class ComodatoMovimento extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const EMPRESTIMO = 'EMPRESTIMO';

    public const DEVOLUCAO = 'DEVOLUCAO';

    public const ESTORNO = 'ESTORNO';

    protected $table = 'comodato_movimentos';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'comodato_id', 'tipo', 'quantidade',
        'saldo_apos', 'data', 'estorna_id', 'observacao', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'saldo_apos' => 'decimal:3',
            'data' => 'date',
        ];
    }

    public function comodato(): BelongsTo
    {
        return $this->belongsTo(Comodato::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** O movimento que este estorna — nulo em tudo que não é ESTORNO. */
    public function estornado(): BelongsTo
    {
        return $this->belongsTo(self::class, 'estorna_id');
    }

    /** Devolução já anulada por um estorno não pode ser estornada de novo. */
    public function foiEstornado(): bool
    {
        return self::query()->where('estorna_id', $this->id)->exists();
    }
}
