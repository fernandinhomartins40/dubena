<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/**
 * F2-02 — `exists:` que respeita a fronteira do tenant.
 *
 * `exists:clientes,id` valida contra a tabela INTEIRA. Provado antes desta
 * regra: um POST /pedidos da empresa A, informando `cliente_id` de um cliente da
 * empresa B, era aceito com HTTP 201 — e continuava aceito entre TENANTS
 * distintos mesmo com o enforcement ligado. A RLS protege a leitura; a validacao
 * corria por fora e deixava a FK nascer cruzada.
 *
 * Aqui a checagem passa pelo MODEL, entao o escopo vem de quem ja o declara:
 * `BelongsToTenant` filtra por empresa, `BelongsToGrupo` por grupo, e um model
 * sem escopo continua valendo em toda a tabela — que e o correto para catalogo
 * de plataforma.
 *
 * Uso: `new ExisteNoTenant(Cliente::class)` no lugar de `exists:clientes,id`.
 */
class ExisteNoTenant implements ValidationRule
{
    /**
     * @param  class-string<Model>  $model
     * @param  string  $coluna  chave comparada (padrao: a primary key)
     */
    public function __construct(
        private string $model,
        private ?string $coluna = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return; // `nullable`/`required` decidem isso, nao esta regra
        }

        /** @var Model $instancia */
        $instancia = new $this->model;
        $coluna = $this->coluna ?? $instancia->getKeyName();

        // `query()` aplica os global scopes do model — e a fronteira.
        if (! $this->model::query()->where($coluna, $value)->exists()) {
            // Mensagem igual a do `exists` nativo: quem esta de fora nao
            // aprende, pelo texto do erro, se o registro existe noutro tenant.
            $fail('validation.exists')->translate(['attribute' => $attribute]);
        }
    }
}
