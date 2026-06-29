<?php

namespace App\Domain\Mobile;

use App\Domain\Monitora\MonitoraService;
use App\Domain\Saas\CidadeService;
use App\Models\Empresa;
use App\Models\EmpresaConfig;
use App\Models\Monitora\Cerca;
use Illuminate\Support\Collection;

/**
 * MarketplaceService (MP1) — descoberta de empresas por geolocalização ("iFood do gás").
 *
 * Dado um ponto (lat/lng), retorna as empresas que ATENDEM ali. A cobertura é resolvida,
 * por empresa, em duas camadas:
 *  1) GEOFENCE: se a empresa tem cerca(s) ativa(s), o ponto precisa cair dentro de uma
 *     (reusa MonitoraService::dentroDaCerca — mesmo ponto-em-polígono do resto do sistema);
 *  2) RAIO (fallback): se não tem cerca, usa `raio_entrega_km` a partir da matriz (lat/lng).
 *
 * É CROSS-EMPRESA por natureza (descoberta pública), então as cercas são lidas com
 * `withoutTenant()` — não há tenant ativo no fluxo público.
 */
class MarketplaceService
{
    public function __construct(
        private MonitoraService $monitora,
        private CidadeService $cidades,
    ) {}

    /**
     * Empresas (aderidas ao marketplace) que atendem o ponto, ordenadas pela distância
     * da matriz (mais perto primeiro).
     *
     * @return Collection<int, array<string,mixed>>
     */
    public function empresasNoPonto(float $lat, float $lng): Collection
    {
        $empresas = Empresa::query()
            ->where('ativo', true)
            ->where('app_marketplace_ativo', true)
            ->get();

        // Cercas ativas por empresa (cross-tenant), carregadas de uma vez.
        $cercasPorEmpresa = Cerca::withoutTenant()
            ->where('ativo', true)
            ->whereIn('empresa_id', $empresas->pluck('id'))
            ->with('pontos')
            ->get()
            ->groupBy('empresa_id');

        // Tempo de entrega (min) por empresa, da config (exibição).
        $tempoPorEmpresa = EmpresaConfig::query()
            ->whereIn('empresa_id', $empresas->pluck('id'))
            ->pluck('tempoentrega', 'empresa_id');

        // Cidade da plataforma resolvida pelo PONTO do cliente (P3): a mesma para
        // todas as empresas (é a cidade do endereço, não da empresa). Resolvida uma vez.
        $cidade = $this->cidades->resolverPorPonto($lat, $lng);
        $cidadePayload = $cidade ? ['id' => $cidade->id, 'nome' => $cidade->nome, 'uf' => $cidade->uf] : null;

        return $empresas
            ->map(function (Empresa $e) use ($lat, $lng, $cercasPorEmpresa, $tempoPorEmpresa, $cidadePayload) {
                $cercas = $cercasPorEmpresa->get($e->id);
                $atende = $this->atende($e, $lat, $lng, $cercas);
                if (! $atende) {
                    return null;
                }

                $distancia = ($e->latitude !== null && $e->longitude !== null)
                    ? round($this->monitora->distanciaMetros((float) $e->latitude, (float) $e->longitude, $lat, $lng) / 1000, 2)
                    : null;

                return [
                    'id' => $e->id,
                    'nome' => $e->nome_fantasia ?: $e->razao_social,
                    'telefone' => $e->telefone1,
                    'latitude' => $e->latitude !== null ? (float) $e->latitude : null,
                    'longitude' => $e->longitude !== null ? (float) $e->longitude : null,
                    'distancia_km' => $distancia,
                    'tempo_entrega_min' => $tempoPorEmpresa->get($e->id),
                    'cidade' => $cidadePayload,
                ];
            })
            ->filter()
            ->sortBy(fn ($e) => $e['distancia_km'] ?? PHP_FLOAT_MAX)
            ->values();
    }

    /**
     * A empresa atende o ponto? Geofence tem prioridade; sem cerca, cai para o raio
     * da matriz. Sem cerca e sem raio/coordenada → não atende (precisa configurar).
     *
     * @param  Collection<int,Cerca>|null  $cercas
     */
    private function atende(Empresa $e, float $lat, float $lng, $cercas): bool
    {
        if ($cercas !== null && $cercas->isNotEmpty()) {
            return $cercas->contains(fn (Cerca $c) => $this->monitora->dentroDaCerca($c, $lat, $lng));
        }

        if ($e->raio_entrega_km !== null && $e->latitude !== null && $e->longitude !== null) {
            $metros = $this->monitora->distanciaMetros((float) $e->latitude, (float) $e->longitude, $lat, $lng);

            return $metros <= ((float) $e->raio_entrega_km) * 1000;
        }

        return false;
    }
}
