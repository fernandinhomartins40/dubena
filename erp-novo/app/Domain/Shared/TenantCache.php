<?php

namespace App\Domain\Shared;

use App\Domain\Tenant\TenantContext;
use Illuminate\Support\Facades\Cache;

/**
 * Cache por TENANT (F13) — prefixa toda chave com o empresa/grupo ativo, de modo
 * que dado cacheado de uma empresa NUNCA seja servido a outra (defense-in-depth do
 * F02 também no cache). Sem tenant resolvido (CLI/ETL), usa o prefixo "global".
 *
 * Uso: app(TenantCache::class)->remember('catalogo', 300, fn () => ...).
 */
class TenantCache
{
    public function __construct(private TenantContext $tenant) {}

    /** Chave namespaced por tenant. */
    public function chave(string $chave): string
    {
        $emp = $this->tenant->empresaId() ?? 'global';
        $grp = $this->tenant->grupoId() ?? 'global';

        return "t:{$grp}:{$emp}:{$chave}";
    }

    /** @template T @param  \Closure():T  $callback @return T */
    public function remember(string $chave, int $segundos, \Closure $callback): mixed
    {
        return Cache::remember($this->chave($chave), $segundos, $callback);
    }

    public function get(string $chave, mixed $default = null): mixed
    {
        return Cache::get($this->chave($chave), $default);
    }

    public function put(string $chave, mixed $valor, int $segundos): void
    {
        Cache::put($this->chave($chave), $valor, $segundos);
    }

    public function forget(string $chave): void
    {
        Cache::forget($this->chave($chave));
    }
}
