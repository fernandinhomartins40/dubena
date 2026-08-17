<?php

namespace Database\Seeders;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Tenant\TenantContext;
use App\Models\Empresa;
use App\Models\Estoque\EstoqueSaldo;
use App\Models\Estoque\Setor;
use App\Models\Monitora\Cerca;
use App\Models\Produto\Produto;
use App\Models\Produto\ProdutoClasse;
use App\Models\Produto\UnidadeMedida;
use Illuminate\Database\Seeder;

/**
 * Marketplace demo (F7) — IDEMPOTENTE, roda em todo deploy de homolog (e no seed
 * local via DemoGuarapuavaSeeder). Garante o cenário mínimo para testar o fluxo
 * multi-revenda do app consumidor em Guarapuava:
 *
 *  1) a MATRIZ aderida ao marketplace (geoloc + raio + cerca da cidade);
 *  2) uma SEGUNDA revenda ("Unidade Batel", mesmo grupo) também aderida, com
 *     setor/produtos/estoque próprios e preços um pouco menores — dá para ver as
 *     duas na descoberta por GPS, comparar preço e validar o ISOLAMENTO
 *     (cliente/pedido/credencial de uma não aparecem na outra).
 *
 * Existe separado do DemoGuarapuavaSeeder porque a massa demo tem guard de
 * volume (pula em banco populado) — este roda SEMPRE, sem duplicar nada.
 */
class MarketplaceDemoSeeder extends Seeder
{
    public function run(): void
    {
        // GATE DE AMBIENTE (T3.7) — este seeder rodava INCONDICIONALMENTE em
        // todo deploy, criando a "Unidade Batel" demo aderida ao marketplace
        // mesmo em banco já populado. Em produção isso é uma revenda fictícia
        // aparecendo na busca por geolocalização do app do consumidor.
        if (app()->environment('production')) {
            $this->command?->warn('MarketplaceDemoSeeder: IGNORADO em produção (dados de demonstração).');

            return;
        }

        $matriz = Empresa::query()->orderBy('id')->first();
        if (! $matriz) {
            return; // banco sem empresa (instalação zerada) — nada a fazer
        }
        $grupoId = (int) $matriz->grupo_id;

        $tenant = app(TenantContext::class);
        $tenantAnterior = [$tenant->empresaId(), $tenant->grupoId()];

        try {
            $this->aderirMatriz($matriz, $grupoId, $tenant);
            $this->segundaRevenda($grupoId, $tenant);
        } finally {
            // Restaura o tenant que o chamador tinha (o Demo seeder continua depois).
            if ($tenantAnterior[0] !== null && $tenantAnterior[1] !== null) {
                $tenant->set($tenantAnterior[0], $tenantAnterior[1]);
            } else {
                $tenant->clear();
            }
        }

        $this->command?->info('MarketplaceDemoSeeder: matriz + Unidade Batel aderidas ao marketplace.');
    }

    /** Matriz no centro de Guarapuava, com raio 15 km + cerca da malha urbana. */
    private function aderirMatriz(Empresa $matriz, int $grupoId, TenantContext $tenant): void
    {
        $matriz->update([
            'latitude' => -25.3935,
            'longitude' => -51.4562,
            'app_marketplace_ativo' => true,
            'raio_entrega_km' => 15,
            'telefone1' => $matriz->telefone1 ?: '(42) 3622-0000',
        ]);

        $tenant->set($matriz->id, $grupoId);
        $setor = Setor::query()->where('empresa_id', $matriz->id)->where('ativo', true)->orderBy('id')->first();

        $cerca = Cerca::firstOrCreate(
            ['empresa_id' => $matriz->id, 'descricao' => 'Área de entrega — Guarapuava'],
            ['grupo_id' => $grupoId, 'setor_id' => $setor?->id, 'cor' => '#FF6200', 'ativo' => true],
        );
        if ($cerca->pontos()->count() === 0) {
            $cerca->pontos()->createMany([
                ['latitude' => -25.350, 'longitude' => -51.500, 'ordem' => 0],
                ['latitude' => -25.350, 'longitude' => -51.410, 'ordem' => 1],
                ['latitude' => -25.430, 'longitude' => -51.410, 'ordem' => 2],
                ['latitude' => -25.430, 'longitude' => -51.500, 'ordem' => 3],
            ]);
        }
    }

    /**
     * Segunda revenda do MESMO grupo — bairro Batel, raio 12 km (sem cerca: exercita
     * o fallback por raio). Produtos próprios com preço ~5% menor que a matriz.
     */
    private function segundaRevenda(int $grupoId, TenantContext $tenant): void
    {
        $batel = Empresa::firstOrCreate(
            ['grupo_id' => $grupoId, 'razao_social' => 'Gás em Casa — Unidade Batel'],
            [
                'nome_fantasia' => 'Gás em Casa Batel',
                'cnpj' => '90390209000155',
                'uf' => 'PR', 'cidade' => 'Guarapuava', 'bairro' => 'Batel',
                'endereco' => 'Rua Guaíra', 'numero' => '1500',
                'telefone1' => '(42) 3624-1100',
                'latitude' => -25.4009,
                'longitude' => -51.4700,
                'matriz' => false, 'ativo' => true,
                'app_marketplace_ativo' => true,
                'raio_entrega_km' => 12,
            ],
        );

        // Tenant da Batel: o BelongsToTenant auto-preenche empresa_id nas criações.
        $tenant->set($batel->id, $grupoId);

        $setor = Setor::firstOrCreate(
            ['empresa_id' => $batel->id, 'descricao' => 'Loja Batel'],
            ['ativo' => true],
        );

        // Classes/unidade são de GRUPO — reusa as do demo (ou cria, em banco enxuto).
        $glp = ProdutoClasse::firstOrCreate(['grupo_id' => $grupoId, 'descricao' => 'GLP'], ['ativo' => true]);
        $agua = ProdutoClasse::firstOrCreate(['grupo_id' => $grupoId, 'descricao' => 'Água'], ['ativo' => true]);
        $un = UnidadeMedida::firstOrCreate(['grupo_id' => $grupoId, 'descricao' => 'Unidade'], ['sigla' => 'UN', 'ativo' => true]);

        $defs = [
            ['Botijão P13 (13kg)', $glp, 114.00, 95.00],
            ['Botijão P13 - Recarga', $glp, 105.00, 88.00],
            ['Botijão P45 (45kg)', $glp, 405.00, 350.00],
            ['Água Mineral 20L', $agua, 13.00, 8.00],
        ];

        $estoque = app(EstoqueService::class);
        foreach ($defs as [$desc, $classe, $venda, $custo]) {
            $produto = Produto::firstOrCreate(
                // empresa_id EXPLÍCITO na chave: produto é por empresa — sem isto o
                // firstOrCreate casaria com o homônimo da matriz.
                ['empresa_id' => $batel->id, 'grupo_id' => $grupoId, 'descricao' => $desc],
                [
                    'produtoclasse_id' => $classe->id, 'unidademedida_id' => $un->id,
                    'preco_venda' => $venda, 'preco_venda_minimo' => round($venda * 0.92, 2),
                    'custo_medio' => $custo, 'dias_giro' => 15, 'ativo' => true,
                ],
            );

            if (EstoqueSaldo::withoutTenant()->where('setor_id', $setor->id)->where('produto_id', $produto->id)->doesntExist()) {
                $estoque->entrada($setor->id, $produto->id, 300, (float) $custo, 'seed-marketplace');
            }
        }
    }
}
