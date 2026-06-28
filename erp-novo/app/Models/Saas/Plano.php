<?php

namespace App\Models\Saas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plano vendável da plataforma (P2) — GLOBAL (sem tenant). Define o preço e os
 * recursos (feature-flags) que a empresa assinante passa a ter. Os recursos
 * ficam em `plano_recurso` (chaves do RecursoCatalogo).
 */
class Plano extends Model
{
    protected $table = 'planos';

    protected $fillable = ['slug', 'nome', 'descricao', 'preco_mensal', 'ativo'];

    protected function casts(): array
    {
        return [
            'preco_mensal' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }

    /** Recursos (feature-flags) liberados pelo plano. */
    public function recursos(): HasMany
    {
        return $this->hasMany(PlanoRecurso::class);
    }

    /** Assinaturas que apontam para este plano. */
    public function assinaturas(): HasMany
    {
        return $this->hasMany(Assinatura::class);
    }

    /** @return list<string> chaves de recurso deste plano */
    public function chavesDeRecurso(): array
    {
        return $this->recursos()->pluck('recurso_chave')->all();
    }
}
