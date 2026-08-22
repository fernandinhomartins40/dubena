<?php

namespace App\Models;

use App\Domain\Shared\Auditavel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use Auditavel;

    protected $table = 'roles';

    protected $fillable = ['grupo_id', 'nome', 'descricao'];

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    /** Condições ABAC (A4) deste papel (escopadas por empresa). */
    public function conditions(): HasMany
    {
        return $this->hasMany(PermissionCondition::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')->withPivot('empresa_id');
    }
}
