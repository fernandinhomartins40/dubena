<?php

namespace App\Domain\Saas;

use App\Domain\Shared\TenantCache;
use App\Domain\Tenant\TenantContext;
use App\Models\Saas\Assinatura;
use App\Models\Saas\RecursoOverride;

/**
 * LicencaService (P2) — resolve os RECURSOS (feature-flags) efetivos de uma
 * empresa, combinando a assinatura vigente (plano) com os overrides por empresa.
 *
 * Regra de resolução (do mais forte para o mais fraco):
 *   1. override habilitado=false → recurso DESLIGADO (bloqueio explícito vence tudo);
 *   2. override habilitado=true  → recurso LIGADO (cortesia/piloto vence o plano);
 *   3. assinatura vigente         → recursos do plano LIGADOS;
 *   4. nada disso                 → recurso DESLIGADO.
 *
 * "Fail-open" controlado: se NÃO existe NENHUMA assinatura para a empresa (cliente
 * legado/pré-SaaS, ou ambiente sem o seeder de planos), a plataforma libera tudo —
 * para não quebrar instalações existentes ao introduzir o licenciamento. A partir
 * do momento em que a empresa tem uma assinatura, a licença passa a valer.
 *
 * Cacheado por tenant (TenantCache) — invalidar ao mudar plano/assinatura/override.
 */
class LicencaService
{
    private const CACHE_TTL = 300; // 5 min

    public function __construct(
        private TenantContext $tenant,
        private TenantCache $cache,
    ) {}

    /** A empresa ativa tem o recurso habilitado? */
    public function recursoHabilitado(string $chave, ?int $empresaId = null): bool
    {
        return in_array($chave, $this->recursosEfetivos($empresaId), true);
    }

    /** Existe assinatura vigente (trial/ativa dentro da janela) para a empresa? */
    public function assinaturaAtiva(?int $empresaId = null): bool
    {
        return $this->assinaturaVigente($empresaId) !== null;
    }

    /**
     * Recursos efetivos da empresa ativa (lista de chaves).
     *
     * @return list<string>
     */
    public function recursosEfetivos(?int $empresaId = null): array
    {
        $empresaId ??= $this->tenant->empresaId();
        if ($empresaId === null) {
            // Sem tenant resolvido (CLI/ETL): sem restrição de licença.
            return RecursoCatalogo::chaves();
        }

        return $this->cache->remember("licenca:recursos:{$empresaId}", self::CACHE_TTL,
            fn () => $this->calcular($empresaId));
    }

    /** Invalida o cache de recursos da empresa (chamar ao mudar plano/assinatura/override). */
    public function invalidar(int $empresaId): void
    {
        $this->cache->forget("licenca:recursos:{$empresaId}");
    }

    /**
     * Calcula os recursos efetivos: base do plano vigente + overrides por empresa.
     *
     * @return list<string>
     */
    private function calcular(int $empresaId): array
    {
        $overrides = RecursoOverride::withoutTenant()
            ->where('empresa_id', $empresaId)
            ->pluck('habilitado', 'recurso_chave'); // chave => bool

        $assinatura = $this->assinaturaVigente($empresaId);

        // Fail-open: empresa SEM nenhuma assinatura → libera tudo (compat pré-SaaS).
        $temAlgumaAssinatura = Assinatura::withoutTenant()->where('empresa_id', $empresaId)->exists();
        if (! $temAlgumaAssinatura && $overrides->isEmpty()) {
            return RecursoCatalogo::chaves();
        }

        // Base: recursos do plano da assinatura vigente (vazio se não vigente).
        $base = $assinatura
            ? $assinatura->plano?->chavesDeRecurso() ?? []
            : [];

        $efetivos = array_fill_keys($base, true);

        // Aplica overrides (sobrepõem o plano em ambos os sentidos).
        foreach ($overrides as $chave => $habilitado) {
            if ($habilitado) {
                $efetivos[$chave] = true;
            } else {
                unset($efetivos[$chave]);
            }
        }

        // Só recursos conhecidos do catálogo (descarta chaves órfãs).
        return array_values(array_filter(
            array_keys($efetivos),
            fn (string $c) => RecursoCatalogo::existe($c),
        ));
    }

    /** A assinatura corrente da empresa, se vigente (senão null). */
    private function assinaturaVigente(?int $empresaId): ?Assinatura
    {
        $empresaId ??= $this->tenant->empresaId();
        if ($empresaId === null) {
            return null;
        }

        $assinatura = Assinatura::withoutTenant()
            ->with('plano.recursos')
            ->where('empresa_id', $empresaId)
            ->whereIn('status', [Assinatura::STATUS_TRIAL, Assinatura::STATUS_ATIVA])
            ->orderByDesc('id')
            ->first();

        return ($assinatura && $assinatura->vigente()) ? $assinatura : null;
    }
}
