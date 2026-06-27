<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Segredo TOTP do usuário (A5). `secret` e `recovery_codes` são cifrados em
 * repouso (cast encrypted). 2FA vale enquanto `habilitado` = true.
 */
class User2fa extends Model
{
    protected $table = 'user_2fa';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = ['user_id', 'secret', 'habilitado', 'confirmado_em', 'recovery_codes'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'recovery_codes' => 'encrypted:array',
            'habilitado' => 'boolean',
            'confirmado_em' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
