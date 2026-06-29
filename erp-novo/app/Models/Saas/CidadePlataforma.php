<?php

namespace App\Models\Saas;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Cidade da PLATAFORMA (P3) — GLOBAL (sem tenant). Catálogo de cidades atendidas
 * pela plataforma SaaS, para descoberta/agrupamento/relatório. Distinta de
 * App\Models\Geografico\Cidade (geográfico por grupo, endereço do cliente).
 * Administrada pelo SuperAdmin (P4).
 */
class CidadePlataforma extends Model
{
    protected $table = 'cidades_plataforma';

    protected $fillable = ['nome', 'uf', 'cod_ibge', 'centro_lat', 'centro_lng', 'ativo'];

    protected function casts(): array
    {
        return [
            'cod_ibge' => 'integer',
            'centro_lat' => 'decimal:7',
            'centro_lng' => 'decimal:7',
            'ativo' => 'boolean',
        ];
    }

    /** Empresas que atuam nesta cidade (vínculo declarativo via empresa_cidade). */
    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class, 'empresa_cidade', 'cidade_plataforma_id', 'empresa_id');
    }
}
