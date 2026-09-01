<?php

namespace App\Domain\Monitora\Drivers;

use App\Domain\Integracao\ConsumoDeIntegracao;
use App\Domain\Monitora\Contracts\AjustadorDeVia;
use App\Domain\Tenant\TenantContext;
use App\Models\ConfigGlobal;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Encaixa o trecho nas ruas usando a Roads API do Google.
 *
 * `interpolate=true` é o que preenche o caminho ENTRE as posições: num trecho
 * medido, 3 pontos distantes viraram 33 grudados na via. É essa interpolação
 * que substitui a reta que atravessava o quarteirão.
 *
 * A API aceita no máximo 100 pontos por chamada e cobra por chamada, por isso
 * quem decide o que enviar é o `AjustadorCacheado` — este driver só traduz o
 * formato e não tem opinião sobre custo.
 */
class RoadsApiAjustador implements AjustadorDeVia
{
    /** Teto da Roads API por requisição. */
    private const MAXIMO_PONTOS = 100;

    /** @param  list<array{lat:float,lng:float}>  $pontos */
    public function ajustar(array $pontos): ?array
    {
        if (count($pontos) < 2) {
            return null;
        }

        $chave = (string) (ConfigGlobal::query()->value('google_maps_key') ?? '');
        if ($chave === '') {
            return null;
        }

        // Acima do teto a API recusa a chamada inteira. Fatiar aqui manteria o
        // custo escondido de quem chama; melhor recusar e deixar o cacheado
        // dividir o trecho conscientemente.
        if (count($pontos) > self::MAXIMO_PONTOS) {
            return null;
        }

        $caminho = implode('|', array_map(
            fn ($p) => sprintf('%.6f,%.6f', $p['lat'], $p['lng']),
            $pontos,
        ));

        try {
            $resp = Http::timeout(20)->acceptJson()
                ->get('https://roads.googleapis.com/v1/snapToRoads', [
                    'path' => $caminho,
                    'interpolate' => 'true',
                    'key' => $chave,
                ]);

            // F6-01 — cobrada por chamada. O Google cobra tendo devolvido
            // traçado ou não, entao registra antes de interpretar.
            $tenant = app(TenantContext::class);
            app(ConsumoDeIntegracao::class)->registrar(
                'roads', $tenant->empresaId(), $tenant->grupoId(), 'encaixar_na_via',
                erro: ! $resp->successful(),
                mensagemErro: $resp->successful() ? null : 'HTTP '.$resp->status(),
            );

            if (! $resp->successful()) {
                Log::warning('Roads API respondeu com erro', ['status' => $resp->status()]);

                return null;
            }

            $saida = [];
            foreach ($resp->json('snappedPoints') ?? [] as $p) {
                $lat = $p['location']['latitude'] ?? null;
                $lng = $p['location']['longitude'] ?? null;
                if ($lat !== null && $lng !== null) {
                    $saida[] = ['lat' => (float) $lat, 'lng' => (float) $lng];
                }
            }

            return count($saida) >= 2 ? $saida : null;
        } catch (\Throwable $e) {
            Log::warning('Falha ao consultar a Roads API: '.$e->getMessage());

            return null;
        }
    }
}
