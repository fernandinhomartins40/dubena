<?php

namespace App\Models\Mobile;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppDevice extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'app_devices';

    protected $fillable = [
        'user_id', 'empresa_id', 'plataforma', 'push_token', 'device_id',
        'app_versao', 'ativo', 'ultimo_acesso',
    ];

    protected function casts(): array
    {
        return ['ativo' => 'boolean', 'ultimo_acesso' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
