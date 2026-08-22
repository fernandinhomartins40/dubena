<?php

namespace App\Models\Cliente;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Par de cadastros suspeitos de serem a mesma pessoa, aguardando decisão humana.
 *
 * Quando esta linha é criada, a VENDA JÁ ACONTECEU: a dúvida é trabalho de
 * retaguarda, nunca trava de balcão.
 */
class ClienteRevisao extends Model
{
    use BelongsToTenant;

    protected $table = 'cliente_revisoes';

    protected $fillable = [
        'empresa_id', 'cliente_id', 'candidato_id', 'escore', 'tracos', 'origem',
        'situacao', 'decidido_por_user_id', 'decidido_em', 'observacao',
    ];

    protected function casts(): array
    {
        return [
            'tracos' => 'array',
            'escore' => 'integer',
            'decidido_em' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function candidato(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'candidato_id');
    }

    public function decidiuUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decidido_por_user_id');
    }
}
