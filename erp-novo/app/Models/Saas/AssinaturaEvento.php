<?php

namespace App\Models\Saas;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Trilha de eventos da assinatura (P2) — TENANT. Auditoria de mudanças de
 * plano/status (criada, plano.alterado, status.alterado, cancelada).
 */
class AssinaturaEvento extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'assinatura_eventos';

    protected $fillable = ['empresa_id', 'assinatura_id', 'tipo', 'detalhes', 'user_id', 'criado_em'];

    protected function casts(): array
    {
        return [
            'detalhes' => 'array',
            'criado_em' => 'datetime',
        ];
    }
}
