<?php

namespace App\Models;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot versionado de um papel (A6) — gravado a cada alteração de
 * nome/descrição/permissões. Base do histórico de permissões. Escopado por
 * empresa (RLS); `criado_em` no banco, sem updated_at.
 */
class RoleVersion extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    public const CREATED_AT = 'criado_em';

    protected $table = 'role_versions';

    protected $fillable = ['empresa_id', 'role_id', 'snapshot', 'alterado_por', 'criado_em'];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'criado_em' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'alterado_por');
    }
}
