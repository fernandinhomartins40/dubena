<?php

namespace Database\Seeders;

use App\Domain\Tenant\TenantContext;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\Monitora\UltimaPosicao;
use App\Models\Monitora\Veiculo as MonitoraVeiculo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

/**
 * Seed de demonstração de MAPA — Guarapuava/PR. Popula o que as telas do erp-novo
 * que tocam Google Maps precisam para mostrar pontos reais:
 *
 *  1. Geográfico: estado PR + cidade Guarapuava + bairros reais.
 *  2. Clientes com endereço real → geocodificados (lat/lng) para o cadastro e o
 *     link do mapa abrirem no ponto certo. Geocoding SÍNCRONO aqui (não depende de
 *     worker de fila); se a GOOGLE_MAPS_KEY faltar/falhar, usa coordenada real do
 *     bairro como fallback — o mapa sempre tem ponto.
 *  3. Frota (Monitora/GPS): veículos com última posição espalhada por Guarapuava
 *     → tela Monitora lista e o link abre o mapa no ponto.
 *
 * Idempotente: reusa a empresa-matriz e não duplica cidade/clientes/veículos.
 * NÃO é para produção real — é massa de demonstração.
 */
class GuarapuavaMapaSeeder extends Seeder
{
    /** Centro de Guarapuava (fallback e base das posições de veículos). */
    private const CENTRO = ['lat' => -25.3935, 'lng' => -51.4562];

    public function run(): void
    {
        $this->call(DeployAdminSeeder::class);

        $empresa = Empresa::query()->orderBy('id')->firstOrFail();
        $grupoId = (int) $empresa->grupo_id;
        app(TenantContext::class)->set($empresa->id, $grupoId);

        Estado::firstOrCreate(['uf' => 'PR'], ['descricao' => 'Paraná']);

        $cidade = Cidade::firstOrCreate(
            ['grupo_id' => $grupoId, 'descricao' => 'Guarapuava', 'uf' => 'PR'],
            ['cod_ibge' => '4109401', 'ativo' => true],
        );

        // Bairros reais de Guarapuava com coordenada aproximada (fallback do geocoding).
        $bairros = [
            'Centro' => ['lat' => -25.3935, 'lng' => -51.4562],
            'Santana' => ['lat' => -25.3870, 'lng' => -51.4490],
            'Trianon' => ['lat' => -25.3790, 'lng' => -51.4640],
            'Batel' => ['lat' => -25.4030, 'lng' => -51.4510],
            'Boqueirão' => ['lat' => -25.4100, 'lng' => -51.4380],
            'Bonsucesso' => ['lat' => -25.3760, 'lng' => -51.4720],
        ];
        $bairroModel = [];
        foreach ($bairros as $nome => $_) {
            $bairroModel[$nome] = Bairro::firstOrCreate(
                ['grupo_id' => $grupoId, 'cidade_id' => $cidade->id, 'descricao' => $nome],
                ['ativo' => true],
            );
        }

        // Clientes com endereço real, geocodificados.
        $clientes = [
            ['nome' => 'Distribuidora Centro Gás', 'endereco' => 'Rua XV de Novembro', 'numero' => '1200', 'bairro' => 'Centro', 'cep' => '85010-090'],
            ['nome' => 'Mercado Santana', 'endereco' => 'Rua Coronel Saturnino', 'numero' => '450', 'bairro' => 'Santana', 'cep' => '85070-000'],
            ['nome' => 'Padaria Trianon', 'endereco' => 'Avenida Manoel Ribas', 'numero' => '2300', 'bairro' => 'Trianon', 'cep' => '85012-000'],
            ['nome' => 'Restaurante Batel', 'endereco' => 'Rua Doutor Néctor', 'numero' => '88', 'bairro' => 'Batel', 'cep' => '85040-000'],
            ['nome' => 'Conveniência Boqueirão', 'endereco' => 'Rua Afonso Botelho', 'numero' => '760', 'bairro' => 'Boqueirão', 'cep' => '85020-000'],
            ['nome' => 'Lanchonete Bonsucesso', 'endereco' => 'Rua Brigadeiro Rocha', 'numero' => '150', 'bairro' => 'Bonsucesso', 'cep' => '85015-000'],
        ];

        $apiKey = config('services.geocoding.key');
        $criados = 0;

        foreach ($clientes as $c) {
            if (Cliente::query()->where('empresa_id', $empresa->id)->where('nome', $c['nome'])->exists()) {
                continue;
            }

            [$lat, $lng, $type] = $this->geocodificar(
                "{$c['endereco']}, {$c['numero']} - {$c['bairro']}, Guarapuava - PR, {$c['cep']}, Brasil",
                $apiKey,
                $bairros[$c['bairro']],
            );

            Cliente::create([
                'empresa_id' => $empresa->id,
                'grupo_id' => $grupoId,
                'nome' => $c['nome'],
                'cliente' => true,
                'ativo' => true,
                'endereco' => $c['endereco'],
                'numero' => $c['numero'],
                'cep' => $c['cep'],
                'uf' => 'PR',
                'cidade_id' => $cidade->id,
                'bairro_id' => $bairroModel[$c['bairro']]->id,
                'latitude' => $lat,
                'longitude' => $lng,
                'location_type' => $type,
            ]);
            $criados++;
        }

        // Frota com última posição GPS espalhada pela cidade.
        $veiculos = [
            ['placa' => 'AAA1A11', 'descricao' => 'Caminhão Gás 01', 'imei' => '350000000000001', 'd' => [0.006, -0.004], 'vel' => 32, 'ign' => true],
            ['placa' => 'BBB2B22', 'descricao' => 'Caminhão Gás 02', 'imei' => '350000000000002', 'd' => [-0.008, 0.005], 'vel' => 0, 'ign' => false],
            ['placa' => 'CCC3C33', 'descricao' => 'Moto Entrega 01', 'imei' => '350000000000003', 'd' => [0.003, 0.009], 'vel' => 48, 'ign' => true],
        ];

        foreach ($veiculos as $v) {
            $veic = MonitoraVeiculo::firstOrCreate(
                ['empresa_id' => $empresa->id, 'placa' => $v['placa']],
                ['grupo_id' => $grupoId, 'descricao' => $v['descricao'], 'imei' => $v['imei'], 'ativo' => true],
            );

            UltimaPosicao::updateOrCreate(
                ['veiculo_id' => $veic->id],
                [
                    'latitude' => self::CENTRO['lat'] + $v['d'][0],
                    'longitude' => self::CENTRO['lng'] + $v['d'][1],
                    'velocidade' => $v['vel'],
                    'ignicao' => $v['ign'],
                    'registrado_em' => now(),
                ],
            );
        }

        $this->command?->info("GuarapuavaMapaSeeder: {$criados} cliente(s) novo(s), ".count($veiculos).' veículo(s) com posição. Geocoding: '.($apiKey ? 'API' : 'fallback (sem key)').'.');
    }

    /**
     * Geocodifica via Google (mesma API do Job) de forma síncrona. Em falha/sem
     * key, devolve a coordenada do bairro (fallback) com type APPROXIMATE.
     *
     * @param  array{lat:float,lng:float}  $fallback
     * @return array{0:float,1:float,2:string}
     */
    private function geocodificar(string $endereco, ?string $apiKey, array $fallback): array
    {
        if ($apiKey) {
            try {
                $resp = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                    'address' => $endereco,
                    'key' => $apiKey,
                ]);
                $loc = $resp->json('results.0.geometry.location');
                $type = $resp->json('results.0.geometry.location_type');
                if (is_array($loc) && isset($loc['lat'], $loc['lng'])) {
                    return [(float) $loc['lat'], (float) $loc['lng'], (string) ($type ?? 'GEOMETRIC_CENTER')];
                }
            } catch (\Throwable) {
                // cai no fallback
            }
        }

        return [$fallback['lat'], $fallback['lng'], 'APPROXIMATE'];
    }
}
