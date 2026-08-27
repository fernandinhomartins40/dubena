<?php

namespace App\Models\Saas;

use App\Models\Grupo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ponte documental e temporaria para cadastros legados compartilhados por grupo.
 *
 * grupo_id nao identifica um tenant. O registro somente permite converter um
 * cadastro legado quando a conta SaaS e a evidencia foram declaradas de forma
 * explicita e a conta ja possui empresa aprovada naquele grupo.
 */
class TenantLegacyGroupScope extends Model
{
    public const STATUS_APPROVED = 'APPROVED';

    protected $fillable = [
        'tenant_account_id', 'grupo_id', 'status', 'approved_at', 'evidence_ref',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function tenantAccount(): BelongsTo
    {
        return $this->belongsTo(TenantAccount::class);
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }
}
