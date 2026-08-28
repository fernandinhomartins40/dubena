<?php

namespace App\Models\Financeiro;

use App\Domain\Tenant\BelongsToGrupo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanoConta extends Model
{
    use BelongsToGrupo;
    use HasFactory;

    protected $table = 'planos_conta';

    protected $fillable = ['tenant_account_id', 'grupo_id', 'pai_id', 'codigo', 'descricao', 'pagarreceber', 'nivel', 'ativo'];

    protected function casts(): array
    {
        return ['nivel' => 'integer', 'ativo' => 'boolean'];
    }
}
