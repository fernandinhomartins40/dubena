<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * N2 — Geográfico: cidades/bairros/ruas paginados, escopo por grupo, FKs.
 */
class GeoTest extends TestCase
{
    use RefreshDatabase;

    private function suporte(): array
    {
        // Estado referenciado pela FK uf das cidades (criadas via API nos testes).
        Estado::firstOrCreate(['uf' => 'SP'], ['descricao' => 'São Paulo', 'cod_ibge' => 35]);

        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);

        return [$user, $empresa];
    }

    public function test_lista_cidades_paginadas_do_grupo(): void
    {
        [$user, $empresa] = $this->suporte();
        Cidade::factory()->create(['grupo_id' => $empresa->grupo_id, 'descricao' => 'Palmital']);
        Cidade::factory()->create(['descricao' => 'Cidade de Outro Grupo']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/geo/cidades')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
    }

    public function test_cria_cidade(): void
    {
        [$user] = $this->suporte();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/geo/cidades', ['descricao' => 'Nova Cidade', 'uf' => 'SP'])
            ->assertCreated()
            ->assertJsonPath('data.descricao', 'Nova Cidade');
    }

    public function test_cria_bairro_exige_cidade_valida(): void
    {
        [$user, $empresa] = $this->suporte();
        $cidade = Cidade::factory()->create(['grupo_id' => $empresa->grupo_id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/geo/bairros', ['descricao' => 'Centro', 'cidade_id' => $cidade->id])
            ->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/geo/bairros', ['descricao' => 'X', 'cidade_id' => 999999])
            ->assertStatus(422);
    }

    public function test_entidade_desconhecida_404(): void
    {
        [$user] = $this->suporte();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/geo/planetas')
            ->assertStatus(404);
    }

    public function test_filtra_bairros_por_cidade(): void
    {
        [$user, $empresa] = $this->suporte();
        $c1 = Cidade::factory()->create(['grupo_id' => $empresa->grupo_id]);
        $c2 = Cidade::factory()->create(['grupo_id' => $empresa->grupo_id]);
        Bairro::factory()->create(['grupo_id' => $empresa->grupo_id, 'cidade_id' => $c1->id]);
        Bairro::factory()->create(['grupo_id' => $empresa->grupo_id, 'cidade_id' => $c2->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/geo/bairros?cidade_id={$c1->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
