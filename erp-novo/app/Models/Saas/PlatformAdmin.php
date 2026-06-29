<?php

namespace App\Models\Saas;

use Database\Factories\Saas\PlatformAdminFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * PlatformAdmin (P4) — operador da PLATAFORMA (SuperAdmin), identidade separada
 * dos `users` de tenant. Autentica pelo guard 'platform' (token Sanctum). 2FA
 * obrigatório. NÃO pertence a nenhuma empresa — é a única identidade autorizada a
 * cruzar tenants, e só através do SuperAdminService (auditado).
 */
class PlatformAdmin extends Authenticatable
{
    /** @use HasFactory<PlatformAdminFactory> */
    use HasApiTokens, HasFactory;

    protected $table = 'platform_admins';

    protected $fillable = [
        'nome', 'email', 'password', 'ativo',
        'twofa_secret', 'twofa_habilitado', 'twofa_recovery_codes', 'twofa_confirmado_em',
    ];

    protected $hidden = [
        'password', 'remember_token', 'twofa_secret', 'twofa_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'ativo' => 'boolean',
            'twofa_habilitado' => 'boolean',
            'twofa_confirmado_em' => 'datetime',
            'twofa_secret' => 'encrypted',
            'twofa_recovery_codes' => 'encrypted:array',
        ];
    }
}
