<?php

namespace App\Models\Saas;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantCompanyGrant extends Model
{
    protected $fillable = [
        'tenant_membership_id', 'tenant_account_id', 'empresa_id', 'tenant_company_id',
        'can_read', 'can_operate', 'approved_at', 'grant_evidence_ref',
    ];

    protected function casts(): array
    {
        return ['can_read' => 'boolean', 'can_operate' => 'boolean', 'approved_at' => 'datetime'];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'tenant_membership_id');
    }

    public function tenantAccount(): BelongsTo
    {
        return $this->belongsTo(TenantAccount::class);
    }

    public function tenantCompany(): BelongsTo
    {
        return $this->belongsTo(TenantCompany::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
