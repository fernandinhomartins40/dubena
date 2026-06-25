<?php

namespace App\Domain\Monitora\Drivers;

use App\Domain\Monitora\Contracts\SgcasaDriver;
use Illuminate\Support\Facades\Http;

/**
 * Driver SGCasa REAL (F12 — GATE GPS). Consome a API de rastreamento do SGCasa por
 * HTTP. Ativado por MONITORA_DRIVER=sgcasa + SGCASA_API_URL/SGCASA_TOKEN; em
 * CI/homolog usa-se o Fake. NÃO é exercido pela suíte (gate externo) — a validação
 * real é na homologação com o provedor.
 */
class SgcasaHttpDriver implements SgcasaDriver
{
    /**
     * @param  list<string>  $imeis
     * @return list<array{imei:string, latitude:float, longitude:float, velocidade:float, ignicao:bool, registrado_em:string}>
     */
    public function buscarPosicoes(array $imeis): array
    {
        if ($imeis === []) {
            return [];
        }

        $url = rtrim((string) config('services.sgcasa.url'), '/');
        $token = (string) config('services.sgcasa.token');
        if ($url === '') {
            return [];
        }

        try {
            $resp = Http::timeout(15)->withToken($token)->acceptJson()
                ->post("{$url}/posicoes", ['imeis' => $imeis]);

            if (! $resp->successful()) {
                return [];
            }

            return collect($resp->json('data') ?? $resp->json() ?? [])
                ->map(fn ($p) => [
                    'imei' => (string) ($p['imei'] ?? ''),
                    'latitude' => (float) ($p['latitude'] ?? 0),
                    'longitude' => (float) ($p['longitude'] ?? 0),
                    'velocidade' => (float) ($p['velocidade'] ?? 0),
                    'ignicao' => (bool) ($p['ignicao'] ?? false),
                    'registrado_em' => (string) ($p['registrado_em'] ?? now()->toIso8601String()),
                ])
                ->filter(fn ($p) => $p['imei'] !== '')
                ->values()->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
