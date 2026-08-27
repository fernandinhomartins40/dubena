<?php

namespace App\Models\Saas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantStagingArtifact extends Model
{
    protected $fillable = ['tenant_account_id', 'owner', 'purpose', 'payload', 'expires_at', 'purged_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'expires_at' => 'datetime', 'purged_at' => 'datetime'];
    }

    public function tenantAccount(): BelongsTo
    {
        return $this->belongsTo(TenantAccount::class);
    }
}
