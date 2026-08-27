<?php

namespace App\Models\Saas;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Conta jurídica SaaS; sem fallback para os vínculos legados. */
class TenantAccount extends Model
{
    public const STATUS_OWNERSHIP_UNRESOLVED = 'OWNERSHIP_UNRESOLVED';

    public const STATUS_ACTIVE = 'ACTIVE';

    protected $fillable = [
        'legal_name', 'document', 'status', 'classified_at', 'classification_evidence_ref',
    ];

    protected function casts(): array
    {
        return ['classified_at' => 'datetime'];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_memberships')->withTimestamps();
    }

    public function companyLinks(): HasMany
    {
        return $this->hasMany(TenantCompany::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class, 'tenant_companies')->withTimestamps();
    }
}
