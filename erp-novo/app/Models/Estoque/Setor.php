<?php

namespace App\Models\Estoque;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Setor (depósito de estoque) — escopo por empresa. Legado: setors.
 */
class Setor extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'setores';

    protected $fillable = ['empresa_id', 'grupo_id', 'descricao', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }
}
