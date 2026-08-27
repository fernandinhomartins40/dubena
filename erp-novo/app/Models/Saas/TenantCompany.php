<?php

namespace App\Models\Saas;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantCompany extends Model
{
    public const STATUS_PENDING_OWNERSHIP = 'PENDING_OWNERSHIP';

    public const STATUS_APPROVED = 'APPROVED';

    protected $fillable = [
        'tenant_account_id', 'empresa_id', 'status', 'approved_at', 'ownership_evidence_ref',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function tenantAccount(): BelongsTo
    {
        return $this->belongsTo(TenantAccount::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
