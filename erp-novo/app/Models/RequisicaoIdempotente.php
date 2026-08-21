<?php

namespace App\Models;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro de requisição idempotente — F7.
 *
 * Guarda a resposta da primeira execução para que o reenvio da fila offline
 * devolva o mesmo resultado em vez de repetir o efeito.
 */
class RequisicaoIdempotente extends Model
{
    use BelongsToTenant;

    protected $table = 'requisicoes_idempotentes';

    protected $fillable = [
        'empresa_id', 'user_id', 'chave', 'rota', 'metodo',
        'payload_hash', 'status_http', 'resposta', 'concluida', 'expira_em',
    ];

    protected function casts(): array
    {
        return [
            'resposta' => 'array',
            'concluida' => 'boolean',
            'expira_em' => 'datetime',
            'status_http' => 'integer',
        ];
    }
}
