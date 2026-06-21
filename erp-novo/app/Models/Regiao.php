<?php

namespace App\Models;

use App\Domain\Tenant\BelongsToGrupo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Região de atendimento/entrega, escopada por grupo. Legado: regioes.
 */
class Regiao extends Model
{
    use BelongsToGrupo;
    use HasFactory;

    protected $table = 'regioes';

    protected $fillable = ['grupo_id', 'descricao', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }
}
