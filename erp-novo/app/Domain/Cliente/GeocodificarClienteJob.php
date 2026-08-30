<?php

namespace App\Domain\Cliente;

use App\Domain\Integracao\IntegracaoTenant;
use App\Domain\Tenant\TenantEnvelope;
use App\Domain\Tenant\TenantEnvelopeDispatch;
use App\Domain\Tenant\TenantEnvelopeJob;
use App\Domain\Tenant\TenantEnvelopeRuntime;
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
    use SerializesModels, TenantEnvelopeJob;

    public int $tries = 3;

    /** Espera crescente: a falha típica aqui é quota/instabilidade da API. */
    public array $backoff = [30, 120];

    /** Uma chamada HTTP pendurada não pode segurar o worker. */
    public int $timeout = 60;

    public function __construct(public int $clienteId)
    {
        if (config('saas_transformation.enforcement.tenant_envelope')) {
            $envelope = app(TenantEnvelopeDispatch::class)->captureOrNull();
            if ($envelope !== null) {
                $this->captureTenantEnvelope($envelope);
            }
        }
    }

    public function handle(?TenantEnvelopeRuntime $runtime = null): void
    {
        if ($this->tenantEnvelopePayload !== null) {
            $this->withinTenantEnvelope($runtime ?? app(TenantEnvelopeRuntime::class), fn (): mixed => $this->executarComEnvelope());

            return;
        }

        $this->executar();
    }

    private function executarComEnvelope(): void
    {
        $envelope = TenantEnvelope::fromPayload($this->tenantEnvelopePayload);
        $envelope->requireOperation($envelope->activeEmpresaId);
        $this->executar($envelope->activeEmpresaId);
    }

    private function executar(?int $empresaId = null): void
    {
        $cliente = Cliente::withoutTenant()
            ->when($empresaId !== null, fn ($query) => $query->where('empresa_id', $empresaId))
            ->find($this->clienteId);
        if (! $cliente || ! $this->temEnderecoSuficiente($cliente)) {
            return;
        }

        // F5 (segurança multi-tenant): a key vem do GRUPO DO CLIENTE, explícito —
        // job roda fora de request (TenantContext vazio) e cair no env silencioso
        // faria a rede consumir a quota da plataforma. Fallback env preservado
        // (Maps não é dinheiro; dev/homolog sem key de grupo continua funcionando).
        $apiKey = app(IntegracaoTenant::class)
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

                return;
            }

            // Resposta OK mas sem coordenada: endereço que o Google não achou.
            // Não é erro de infraestrutura — repetir daria o mesmo resultado —,
            // mas precisa ficar registrado: é o sinal de cadastro ruim que
            // alimenta a fila de inconsistências do geográfico.
            Log::info('Geocoding sem resultado para o endereço', [
                'cliente_id' => $this->clienteId,
                'status' => $resp->json('status'),
            ]);
        } catch (\Throwable $e) {
            // T5.0/T5.1: relançar é o ponto. Engolir aqui fazia `$tries = 3`
            // virar decoração — o job "sucedia" na primeira tentativa e a
            // falha transitória (timeout, 5xx da API) nunca era repetida.
            Log::warning('Geocoding do cliente falhou', [
                'cliente_id' => $this->clienteId,
                'tentativa' => $this->attempts(),
                'erro' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Esgotadas as tentativas, o cliente fica sem coordenada.
     *
     * Consequência concreta: ele não aparece no mapa da central e a
     * distribuição automática não consegue medir distância até ele — cai para
     * atribuição manual. Registrar é o que permite reprocessar depois.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('Geocoding desistiu apos todas as tentativas', [
            'cliente_id' => $this->clienteId,
            'erro' => $e->getMessage(),
        ]);
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
