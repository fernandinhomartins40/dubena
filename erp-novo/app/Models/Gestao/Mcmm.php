<?php

namespace App\Models\Gestao;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** MCMM — movimentação de material — C11. Escopo por empresa. */
class Mcmm extends Model
{
    use BelongsToTenant;

    protected $table = 'mcmms';

    protected $fillable = ['empresa_id', 'data', 'descricao', 'tipo', 'quantidade', 'produto_id'];

    protected function casts(): array
    {
        return ['data' => 'date', 'quantidade' => 'decimal:4'];
    }
}
