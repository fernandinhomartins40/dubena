<?php

namespace App\Models\Saas;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantMembership extends Model
{
    public const STATUS_PENDING = 'PENDING';

    public const STATUS_ACTIVE = 'ACTIVE';

    protected $fillable = [
        'tenant_account_id', 'user_id', 'status', 'membership_role', 'approved_at', 'approval_evidence_ref',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function tenantAccount(): BelongsTo
    {
        return $this->belongsTo(TenantAccount::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function companyGrants(): HasMany
    {
        return $this->hasMany(TenantCompanyGrant::class);
    }
}
