<?php

namespace App\Models\Saas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantNetworkLink extends Model
{
    protected $fillable = [
        'provider_tenant_account_id', 'consumer_tenant_account_id', 'relationship_type',
        'status', 'terms_reference', 'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(TenantAccount::class, 'provider_tenant_account_id');
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(TenantAccount::class, 'consumer_tenant_account_id');
    }
}
