<?php

namespace App\Models\Geografico;

use App\Domain\Tenant\BelongsToGrupo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cidade — escopada por grupo. Legado: cidades.
 */
class Cidade extends Model
{
    use BelongsToGrupo;
    use HasFactory;

    protected $table = 'cidades';

    protected $fillable = ['grupo_id', 'descricao', 'uf', 'cod_ibge', 'ativo'];

    protected function casts(): array
    {
        return ['cod_ibge' => 'integer', 'ativo' => 'boolean'];
    }

    public function bairros(): HasMany
    {
        return $this->hasMany(Bairro::class);
    }

    public function ruas(): HasMany
    {
        return $this->hasMany(Rua::class);
    }
}
