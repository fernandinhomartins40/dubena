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
    /**
     * Raio máximo (km) considerado no pré-filtro por bounding-box (P9). Empresas
     * cujo ponto fica fora desta caixa só entram se tiverem GEOFENCE (cerca), que
     * não é limitada por raio. Cobre com folga raios de entrega realistas.
     */
    private const BBOX_RAIO_MAX_KM = 60.0;

    public function empresasNoPonto(float $lat, float $lng): Collection
    {
        // P9 — pré-filtro no banco: recorta as candidatas por bounding-box antes do
        // cálculo de Haversine em PHP. Empresas COM cerca entram sempre (a cerca não
        // é limitada por raio); empresas SEM cerca só entram se caírem na caixa.
        // O passo de precisão (atende()) continua idêntico — isto só reduz o N.
        [$latMin, $latMax, $lngMin, $lngMax] = $this->boundingBox($lat, $lng, self::BBOX_RAIO_MAX_KM);

        $empresas = Empresa::query()
            ->where('ativo', true)
            ->where('app_marketplace_ativo', true)
            ->where(function ($q) use ($latMin, $latMax, $lngMin, $lngMax) {
                // Dentro da caixa (raio-fallback)…
                $q->where(function ($b) use ($latMin, $latMax, $lngMin, $lngMax) {
                    $b->whereBetween('latitude', [$latMin, $latMax])
                        ->whereBetween('longitude', [$lngMin, $lngMax]);
                })
                    // …ou tem cerca ativa (geofence não é limitada por raio).
                    ->orWhereExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('monitora_cercas')
                            ->whereColumn('monitora_cercas.empresa_id', 'empresas.id')
                            ->where('monitora_cercas.ativo', true);
                    });
            })
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
     * A empresa atende o ponto? (F7 — usado pela VALIDAÇÃO DE PEDIDO, além da
     * descoberta.) Escolher a loja no app é UX; a cobertura é revalidada aqui,
     * server-side, na criação do pedido.
     */
    public function empresaAtendePonto(int $empresaId, float $lat, float $lng): bool
    {
        $empresa = Empresa::query()->find($empresaId);
        if (! $empresa) {
            return false;
        }

        return $this->atende($empresa, $lat, $lng, $this->cercasDe($empresaId));
    }

    /**
     * A empresa DECLAROU alguma área de entrega? (F6-05)
     *
     * Pergunta diferente de "atende este ponto", e a distinção é o que separa
     * cobertura de canal.
     *
     * `atende()` devolve `false` tanto para "está fora da minha área" quanto
     * para "não configurei área nenhuma". Confundir os dois transformaria a
     * validação de cobertura num bloqueio para toda revenda que ainda não
     * desenhou a cerca — que hoje é a maioria.
     *
     * Então a regra é: **quem declarou área, respeita a área**; quem não
     * declarou não é restringido — e é a configuração, não a flag de canal, que
     * decide isso.
     */
    public function empresaTemCoberturaDeclarada(int $empresaId): bool
    {
        $empresa = Empresa::query()->find($empresaId);

        if (! $empresa) {
            return false;
        }

        if ($this->cercasDe($empresaId)->isNotEmpty()) {
            return true;
        }

        return $empresa->raio_entrega_km !== null
            && $empresa->latitude !== null
            && $empresa->longitude !== null;
    }

    /** @return Collection<int,Cerca> */
    private function cercasDe(int $empresaId): Collection
    {
        return Cerca::withoutTenant()
            ->where('ativo', true)
            ->where('empresa_id', $empresaId)
            ->with('pontos')
            ->get();
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

    /**
     * Bounding-box (lat/lng) ao redor de um ponto para um raio em km (P9).
     * Aproximação suficiente para PRÉ-FILTRO (a precisão fica no Haversine):
     * 1° de latitude ≈ 111 km; longitude é corrigida pelo cosseno da latitude.
     *
     * @return array{0:float,1:float,2:float,3:float} [latMin, latMax, lngMin, lngMax]
     */
    private function boundingBox(float $lat, float $lng, float $raioKm): array
    {
        $dLat = $raioKm / 111.0;
        $cos = max(cos(deg2rad($lat)), 0.01); // evita divisão por ~0 perto dos polos
        $dLng = $raioKm / (111.0 * $cos);

        return [$lat - $dLat, $lat + $dLat, $lng - $dLng, $lng + $dLng];
    }
}
