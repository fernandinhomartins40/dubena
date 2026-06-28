<?php

namespace App\Models\Saas;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Override de recurso por empresa (P2) — TENANT. Liga/desliga um recurso pontual
 * sobrepondo o plano (cortesia, piloto, bloqueio administrativo). `recurso_chave`
 * é uma chave do RecursoCatalogo. Administrado apenas pelo SuperAdmin (P4).
 */
class RecursoOverride extends Model
{
    use BelongsToTenant;

    protected $table = 'recurso_overrides';

    protected $fillable = ['empresa_id', 'recurso_chave', 'habilitado'];

    protected function casts(): array
    {
        return ['habilitado' => 'boolean'];
    }
}
