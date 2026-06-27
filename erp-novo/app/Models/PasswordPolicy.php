<?php

namespace App\Models;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Política de senha por empresa (A5). Escopada por empresa (BelongsToTenant →
 * global scope + RLS). PK = empresa_id (1:1 com a empresa).
 */
class PasswordPolicy extends Model
{
    use BelongsToTenant;

    protected $table = 'password_policies';

    protected $primaryKey = 'empresa_id';

    public $incrementing = false;

    protected $fillable = ['empresa_id', 'min_len', 'exige_complexidade', 'expira_dias'];

    protected function casts(): array
    {
        return [
            'exige_complexidade' => 'boolean',
            'min_len' => 'integer',
            'expira_dias' => 'integer',
        ];
    }
}
