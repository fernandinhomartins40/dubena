<?php

namespace App\Models;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Condição ABAC (A4) amarrada a (papel, permissão). Limita o exercício da
 * permissão por atributo do recurso: limite de valor, ownership ou horário.
 * Escopada por empresa (BelongsToTenant → global scope + RLS).
 */
class PermissionCondition extends Model
{
    use BelongsToTenant;

    /** Tipos suportados pelo PolicyEvaluator. */
    public const TIPOS = ['limite', 'ownership', 'horario'];

    protected $table = 'permission_conditions';

    protected $fillable = ['empresa_id', 'role_id', 'permission_id', 'tipo', 'parametros', 'ativo'];

    protected function casts(): array
    {
        return [
            'parametros' => 'array',
            'ativo' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}
