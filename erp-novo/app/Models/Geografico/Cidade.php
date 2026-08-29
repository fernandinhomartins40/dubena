<?php

namespace App\Models\Geografico;

use App\Domain\Tenant\BelongsToGrupo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cidade — escopada por grupo. Legado: cidades.
 */
class Cidade extends Model
{
    use BelongsToGrupo;
    use HasFactory;

    protected $table = 'cidades';

    protected $fillable = ['tenant_account_id', 'grupo_id', 'descricao', 'uf', 'cod_ibge', 'municipio_ibge', 'ativo'];

    protected function casts(): array
    {
        return ['cod_ibge' => 'integer', 'municipio_ibge' => 'integer', 'ativo' => 'boolean'];
    }

    /**
     * Município oficial correspondente no catálogo do IBGE.
     *
     * Nullable porque a base real tem cidade que não casa com município nenhum
     * (ex.: a própria "DESCONHECIDO"). Quando preenchido, é a garantia de que
     * o `cod_ibge` enviado na NF-e é o código oficial.
     */
    public function municipio(): BelongsTo
    {
        return $this->belongsTo(MunicipioIbge::class, 'municipio_ibge', 'cod_ibge');
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
