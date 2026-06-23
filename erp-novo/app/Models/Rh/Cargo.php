<?php

namespace App\Models\Rh;

use App\Domain\Tenant\BelongsToGrupo;
use Illuminate\Database\Eloquent\Model;

/**
 * Cargo (função) — escopo por grupo. C5.
 */
class Cargo extends Model
{
    use BelongsToGrupo;

    protected $table = 'cargos';

    protected $fillable = ['grupo_id', 'descricao', 'salario_base', 'ativo'];

    protected function casts(): array
    {
        return ['salario_base' => 'decimal:2', 'ativo' => 'boolean'];
    }
}
