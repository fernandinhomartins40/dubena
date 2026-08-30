<?php

namespace Tests\Feature;

use App\Domain\Saas\CidadeService;
use App\Domain\Tenant\TenantContext;
use App\Models\Empresa;
use App\Models\Saas\CidadePlataforma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P3 — Cidade da plataforma (geolocalização-first). Catálogo global, resolução por
 * ponto, vínculo empresa↔cidade e a rotulagem da cidade na descoberta do marketplace.
 * Cidade NÃO é tenancy: o isolamento por empresa segue intacto (coberto por
 * FaseF02CrossTenantTest/RlsCoberturaTest).
 */
class CidadePlataformaTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->user = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
        ]);
        app(TenantContext::class)->set($this->empresa->id, (int) $this->empresa->grupo_id);
    }

    private function cidade(string $nome, string $uf, ?float $lat, ?float $lng): CidadePlataforma
    {
        return CidadePlataforma::query()->create([
            'nome' => $nome, 'uf' => $uf, 'centro_lat' => $lat, 'centro_lng' => $lng, 'ativo' => true,
        ]);
    }

    public function test_resolve_cidade_mais_proxima_do_ponto(): void
    {
        $gpva = $this->cidade('Guarapuava', 'PR', -25.3935, -51.4620);
        $this->cidade('Curitiba', 'PR', -25.4284, -49.2733);

        // Ponto perto de Guarapuava.
        $resolvida = app(CidadeService::class)->resolverPorPonto(-25.39, -51.46);
        $this->assertSame($gpva->id, $resolvida?->id);
    }

    public function test_cidade_inativa_nao_e_resolvida(): void
    {
        $c = $this->cidade('Guarapuava', 'PR', -25.39, -51.46);
        $c->update(['ativo' => false]);

        $this->assertNull(app(CidadeService::class)->resolverPorPonto(-25.39, -51.46));
    }

    public function test_endpoint_publico_lista_cidades_ativas(): void
    {
        $this->cidade('Guarapuava', 'PR', -25.39, -51.46);
        $inativa = $this->cidade('Cascavel', 'PR', -24.95, -53.45);
        $inativa->update(['ativo' => false]);

        $this->getJson('/api/app/v1/marketplace/cidades')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['nome' => 'Guarapuava', 'uf' => 'PR']);
    }

    public function test_marketplace_rotula_a_cidade_resolvida(): void
    {
        $this->cidade('Guarapuava', 'PR', -25.3935, -51.4620);

        // Empresa aderida ao marketplace, com raio de cobertura a partir da matriz.
        $this->empresa->update([
            'app_marketplace_ativo' => true,
            'raio_entrega_km' => 10,
            'latitude' => -25.3935, 'longitude' => -51.4620,
        ]);

        $resp = $this->postJson('/api/app/v1/marketplace/empresas', [
            'latitude' => -25.39, 'longitude' => -51.46,
        ])->assertOk();

        $resp->assertJsonPath('data.0.cidade.nome', 'Guarapuava');
        $resp->assertJsonPath('data.0.cidade.uf', 'PR');
    }

    public function test_admin_cria_cidade_e_vincula_a_empresa(): void
    {
        // Cria cidade pelo admin.
        $cidadeId = $this->actingAs($this->user, 'sanctum')->postJson('/api/admin/cidades', [
            'nome' => 'Guarapuava', 'uf' => 'PR', 'centro_lat' => -25.39, 'centro_lng' => -51.46,
        ])->assertCreated()->json('data.id');

        // Vincula a empresa ativa à cidade.
        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/admin/empresas/{$this->empresa->id}/cidades", ['cidade_ids' => [$cidadeId]])
            ->assertOk()
            ->assertJsonFragment(['nome' => 'Guarapuava']);

        $this->assertDatabaseHas('empresa_cidade', [
            'empresa_id' => $this->empresa->id, 'cidade_plataforma_id' => $cidadeId,
        ]);
    }

    public function test_vinculo_empresa_cidade_e_isolado_por_empresa(): void
    {
        // Cidade global + vínculo da empresa A.
        $cidade = $this->cidade('Guarapuava', 'PR', -25.39, -51.46);
        app(CidadeService::class)->definirCidadesDaEmpresa($this->empresa, [$cidade->id]);

        // Outra empresa (tenant B): não enxerga o vínculo da empresa A.
        $empresaB = Empresa::factory()->create();
        $userB = User::factory()->create([
            'empresa_id' => $empresaB->id, 'grupo_id' => $empresaB->grupo_id,
        ]);
        app(TenantContext::class)->set($empresaB->id, (int) $empresaB->grupo_id);

        $this->assertSame(0, $empresaB->cidadesPlataforma()->count());
        // A cidade (catálogo global) continua visível para ambas.
        $this->assertSame(1, CidadePlataforma::query()->where('nome', 'Guarapuava')->count());

        // Restaura o tenant A.
        app(TenantContext::class)->set($this->empresa->id, (int) $this->empresa->grupo_id);
        $this->assertSame(1, $this->empresa->cidadesPlataforma()->count());
    }
}
