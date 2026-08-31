<?php

namespace App\Models\Saas;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plano da plataforma (P2) — GLOBAL (sem tenant). Define o preço e os recursos
 * (feature-flags) que a empresa assinante passa a ter. Os recursos ficam em
 * `plano_recurso` (chaves do RecursoCatalogo).
 *
 * `transitorio` separa o que se VENDE do que só existe para conservar acesso
 * durante uma transição (F2-04, `Legacy Full`). Um plano transitório precisa
 * estar `ativo` — senão as assinaturas que apontam para ele deixam de valer —
 * mas não pode ser oferecido nem atribuído a quem chega depois.
 */
class Plano extends Model
{
    protected $table = 'planos';

    /** Slug do plano de transição criado por F2-04. */
    public const SLUG_LEGADO = 'legacy-full';

    protected $fillable = ['slug', 'nome', 'descricao', 'preco_mensal', 'ativo', 'transitorio'];

    protected function casts(): array
    {
        return [
            'preco_mensal' => 'decimal:2',
            'ativo' => 'boolean',
            'transitorio' => 'boolean',
        ];
    }

    /** Planos que a plataforma efetivamente oferece. */
    public function scopeVendaveis(Builder $q): Builder
    {
        return $q->where('ativo', true)->where('transitorio', false);
    }

    /** Recursos (feature-flags) liberados pelo plano. */
    public function recursos(): HasMany
    {
        return $this->hasMany(PlanoRecurso::class);
    }

    /** Tetos numéricos do plano; ausência de linha significa ilimitado. */
    public function limites(): HasMany
    {
        return $this->hasMany(PlanoLimite::class);
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
