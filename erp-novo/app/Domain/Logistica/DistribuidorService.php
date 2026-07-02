<?php

namespace App\Domain\Logistica;

use App\Models\Logistica\Jornada;
use App\Models\Logistica\LogisticaConfig;
use App\Models\Mobile\EntregadorPosicao;
use App\Models\Pedido\Pedido;

/**
 * DistribuidorService (L3) — sugere o MELHOR entregador para um pedido, ranqueando
 * os que estão EM JORNADA por:
 *   - proximidade (posição do celular do entregador × coordenada do cliente);
 *   - carga atual (nº de pedidos ativos — menos é melhor);
 *   - urgência do pedido (empurra o mais próximo/menos carregado).
 *
 * Candidatos bloqueados são excluídos. Distância via Haversine (grátis; a L5 pode
 * trocar por Distance Matrix onde valer o custo). Pesos default embutidos; a L3
 * completa lê `logistica_config` por empresa para ajustá-los.
 *
 * Retorna um ranking (não decide sozinho): o modo `sugerir` (decisão de produto)
 * mostra o topo ao operador; o modo `auto` chamaria `atribuir()` com o 1º.
 */
class DistribuidorService
{
    /** Pesos default (0..1). Proximidade domina; carga desempata. */
    private const PESO_DISTANCIA = 0.7;

    private const PESO_CARGA = 0.3;

    public function __construct(private CentralService $central) {}

    /**
     * Ranking de entregadores para o pedido (melhor primeiro).
     *
     * @return list<array<string,mixed>>
     */
    public function ranquear(Pedido $pedido): array
    {
        $empresaId = (int) $pedido->empresa_id;
        $lat = $pedido->cliente?->latitude !== null ? (float) $pedido->cliente->latitude : null;
        $lng = $pedido->cliente?->longitude !== null ? (float) $pedido->cliente->longitude : null;

        // Config por empresa (pesos/raio/teto). Sem config, usa os defaults.
        $config = LogisticaConfig::query()->where('empresa_id', $empresaId)->first();
        $pesoDist = $config?->peso_distancia ?? self::PESO_DISTANCIA;
        $pesoCarga = $config?->peso_carga ?? self::PESO_CARGA;
        $raioMax = $config?->raio_maximo_km;
        $tetoCarga = $config?->teto_carga;

        $bloqueados = $this->central->entregadoresBloqueados($empresaId);
        $carga = $this->central->cargaPorEntregador($empresaId);

        // Candidatos = entregadores em jornada ativa (não bloqueados).
        $jornadas = Jornada::query()
            ->where('empresa_id', $empresaId)->where('status', 'ativa')
            ->with(['entregador:id,name'])
            ->get()
            ->reject(fn (Jornada $j) => in_array((int) $j->entregador_user_id, $bloqueados, true));

        if ($jornadas->isEmpty()) {
            return [];
        }

        $posicoes = EntregadorPosicao::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('entregador_user_id', $jornadas->pluck('entregador_user_id'))
            ->get()->keyBy('entregador_user_id');

        $maxCarga = max(1, (int) (collect($carga)->max() ?? 0));

        $ranked = $jornadas->map(function (Jornada $j) use ($lat, $lng, $posicoes, $carga, $maxCarga, $pesoDist, $pesoCarga, $raioMax, $tetoCarga) {
            $uid = (int) $j->entregador_user_id;
            $pos = $posicoes->get($uid);

            $distanciaKm = ($lat !== null && $lng !== null && $pos)
                ? $this->haversineKm($lat, $lng, (float) $pos->latitude, (float) $pos->longitude)
                : null;

            $cargaAtual = (int) ($carga[$uid] ?? 0);

            // Score 0..1 (maior = melhor). Sem geo, distância vira neutra (0.5).
            $scoreDist = $distanciaKm === null ? 0.5 : 1 / (1 + $distanciaKm); // decai com a distância
            $scoreCarga = 1 - ($cargaAtual / $maxCarga);
            $score = $pesoDist * $scoreDist + $pesoCarga * $scoreCarga;

            return [
                'entregador_user_id' => $uid,
                'nome' => $j->entregador?->name,
                'veiculo_id' => $j->veiculo_id,
                'distancia_km' => $distanciaKm !== null ? round($distanciaKm, 2) : null,
                'carga' => $cargaAtual,
                'score' => round($score, 4),
                // Fora do raio/teto → inelegível para auto-atribuição (mas ainda listado).
                'elegivel' => ($raioMax === null || $distanciaKm === null || $distanciaKm <= $raioMax)
                    && ($tetoCarga === null || $cargaAtual < $tetoCarga),
            ];
        });

        return $ranked->sortByDesc('score')->values()->all();
    }

    /** O melhor candidato ELEGÍVEL (ou null). Base do modo `auto`. */
    public function melhorEntregador(Pedido $pedido): ?array
    {
        foreach ($this->ranquear($pedido) as $cand) {
            if ($cand['elegivel'] ?? true) {
                return $cand;
            }
        }

        return null;
    }

    /** Haversine em km. */
    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
