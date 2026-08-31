<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Trilha de tentativas de login (A5) — base do lockout e da auditoria de
 * segurança. NÃO é tenant-scoped (login ocorre antes do tenant). `criado_em`
 * é gerenciado pelo banco (sem updated_at).
 */
class LoginLog extends Model
{
    public const UPDATED_AT = null;

    public const CREATED_AT = 'criado_em';

    protected $table = 'login_logs';

    protected $fillable = ['user_id', 'email', 'empresa_id', 'ip', 'user_agent', 'sucesso', 'motivo', 'criado_em',
        'tenant_account_id', 'correlation_id'];

    protected function casts(): array
    {
        return [
            'sucesso' => 'boolean',
            'criado_em' => 'datetime',
        ];
    }
}
