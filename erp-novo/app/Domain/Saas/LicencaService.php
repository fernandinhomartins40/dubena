<?php

namespace App\Domain\Saas;

use App\Domain\Shared\TenantCache;
use App\Domain\Tenant\TenantContext;
use App\Models\Saas\Assinatura;
use App\Models\Saas\LimiteOverride;
use App\Models\Saas\PlanoLimite;
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
            return [];
        }

        return $this->cache->remember("licenca:recursos:{$empresaId}", self::CACHE_TTL,
            fn () => $this->calcular($empresaId));
    }

    /** Invalida o cache de recursos da empresa (chamar ao mudar plano/assinatura/override). */
    public function invalidar(int $empresaId): void
    {
        $this->cache->forget("licenca:recursos:{$empresaId}");
        $this->cache->forget("licenca:limites:{$empresaId}");
    }

    /**
     * Teto contratado para um limite. `null` = ilimitado — F2-03.
     *
     * Sem assinatura vigente o teto é ZERO, não ilimitado: é o mesmo
     * fail-closed dos recursos. Quem não contratou não cria nada.
     *
     * Ordem: override da empresa (cortesia/piloto) > limite do plano > ausência
     * de declaração, que significa ilimitado — não inventamos teto para plano
     * que nunca o declarou.
     */
    public function limite(string $chave, ?int $empresaId = null): ?int
    {
        $empresaId ??= $this->tenant->empresaId();
        if ($empresaId === null) {
            return 0;
        }

        $limites = $this->cache->remember("licenca:limites:{$empresaId}", self::CACHE_TTL,
            fn () => $this->calcularLimites($empresaId));

        // A chave só some do mapa quando o plano não a declara: ilimitado.
        return array_key_exists($chave, $limites) ? $limites[$chave] : null;
    }

    /**
     * O uso atual cabe no teto contratado?
     *
     * `$aCriar` é quanto se pretende adicionar — a pergunta é sempre "posso
     * criar mais um?", não "já estourei?".
     */
    public function dentroDoLimite(string $chave, int $usoAtual, ?int $empresaId = null, int $aCriar = 1): bool
    {
        $teto = $this->limite($chave, $empresaId);

        return $teto === null || ($usoAtual + $aCriar) <= $teto;
    }

    /**
     * Limites efetivos da empresa: plano + overrides vigentes.
     *
     * @return array<string, int|null>
     */
    private function calcularLimites(int $empresaId): array
    {
        $assinatura = $this->assinaturaVigente($empresaId);
        if ($assinatura === null) {
            // Fail-closed: sem contrato, teto zero em tudo que o catálogo conhece.
            return array_fill_keys(RecursoCatalogo::chavesDeLimite(), 0);
        }

        $limites = PlanoLimite::query()
            ->where('plano_id', $assinatura->plano_id)
            ->pluck('valor', 'limite_chave')
            ->map(fn ($v) => $v === null ? null : (int) $v)
            ->all();

        // Override expirado não vale: cortesia com prazo tem de acabar sozinha.
        $overrides = LimiteOverride::withoutTenant()
            ->where('empresa_id', $empresaId)
            ->where(fn ($q) => $q->whereNull('expira_em')->orWhere('expira_em', '>', now()))
            ->pluck('valor', 'limite_chave');

        foreach ($overrides as $chave => $valor) {
            $limites[$chave] = $valor === null ? null : (int) $valor;
        }

        return $limites;
    }

    /**
     * Calcula os recursos efetivos: base do plano vigente + overrides por empresa.
     *
     * @return list<string>
     */
    private function calcular(int $empresaId): array
    {
        // Override expirado deixa de valer (F2-03): cortesia e piloto têm prazo,
        // e sem esta cláusula um "30 dias" viraria permanente por esquecimento.
        // `expira_em` nulo = sem prazo, que é o caso das linhas antigas.
        $overrides = RecursoOverride::withoutTenant()
            ->where('empresa_id', $empresaId)
            ->where(fn ($q) => $q->whereNull('expira_em')->orWhere('expira_em', '>', now()))
            ->pluck('habilitado', 'recurso_chave'); // chave => bool

        $assinatura = $this->assinaturaVigente($empresaId);

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
