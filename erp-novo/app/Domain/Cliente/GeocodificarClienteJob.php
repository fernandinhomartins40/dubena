<?php

namespace App\Domain\Cliente;

use App\Models\Cliente\Cliente;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Geocodifica o endereço do cliente de forma ASSÍNCRONA (fila), substituindo a
 * chamada síncrona do legado que travava o request. Gate externo (geocoding) —
 * provedor via config; falha não derruba o cadastro.
 */
class GeocodificarClienteJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public int $clienteId)
    {
    }

    public function handle(): void
    {
        $cliente = Cliente::withoutTenant()->find($this->clienteId);
        if (! $cliente || ! $this->temEnderecoSuficiente($cliente)) {
            return;
        }

        // F5 (segurança multi-tenant): a key vem do GRUPO DO CLIENTE, explícito —
        // job roda fora de request (TenantContext vazio) e cair no env silencioso
        // faria a rede consumir a quota da plataforma. Fallback env preservado
        // (Maps não é dinheiro; dev/homolog sem key de grupo continua funcionando).
        $apiKey = app(\App\Domain\Integracao\IntegracaoTenant::class)
            ->googleMapsKey($cliente->grupo_id !== null ? (int) $cliente->grupo_id : null);
        if (empty($apiKey)) {
            return; // sem credencial (ex.: dev/homolog) — não geocodifica
        }

        $endereco = $this->montarEndereco($cliente);

        try {
            $resp = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $endereco,
                'key' => $apiKey,
            ]);

            $loc = $resp->json('results.0.geometry.location');
            $type = $resp->json('results.0.geometry.location_type');

            if (is_array($loc) && isset($loc['lat'], $loc['lng'])) {
                $cliente->forceFill([
                    'latitude' => $loc['lat'],
                    'longitude' => $loc['lng'],
                    'location_type' => $type,
                ])->save();
            }
        } catch (\Throwable $e) {
            Log::warning('Geocoding do cliente falhou', ['cliente_id' => $this->clienteId, 'erro' => $e->getMessage()]);
        }
    }

    private function temEnderecoSuficiente(Cliente $c): bool
    {
        return ! empty($c->endereco) && ! empty($c->cidade_id);
    }

    private function montarEndereco(Cliente $c): string
    {
        $partes = array_filter([
            trim(($c->endereco ?? '').', '.($c->numero ?? '')),
            optional($c->cidade)->descricao,
            $c->uf,
            $c->cep,
            'Brasil',
        ]);

        return implode(', ', $partes);
    }
}
