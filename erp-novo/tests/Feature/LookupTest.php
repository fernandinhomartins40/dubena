<?php

namespace Tests\Feature;

use App\Domain\Tenant\TenantContext;
use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lookups (/lookups/{tipo}) — alimentam os AsyncSelect da SPA. Shape {id,label}.
 * Eram 29 endpoints AUSENTES → todos os dropdowns da SPA vinham vazios.
 */
class LookupTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $empresa = Empresa::factory()->create();
        $u = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);

        return $u;
    }

    public function test_lookup_de_tabela_retorna_id_label(): void
    {
        $u = $this->user();
        Produto::create(['empresa_id' => $u->empresa_id, 'grupo_id' => $u->grupo_id, 'descricao' => 'Botijão P13', 'preco_venda' => 100, 'custo_medio' => 80, 'ativo' => true]);

        $this->actingAs($u, 'sanctum')
            ->getJson('/api/admin/lookups/produtos')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'label']]])
            ->assertJsonPath('data.0.label', 'Botijão P13');
    }

    public function test_lookup_de_bairros_recorta_pela_cidade(): void
    {
        $u = $this->user();
        Estado::firstOrCreate(['uf' => 'PR'], ['descricao' => 'Paraná', 'cod_ibge' => 41]);

        $guarapuava = Cidade::factory()->create(['grupo_id' => $u->grupo_id, 'descricao' => 'Guarapuava', 'uf' => 'PR']);
        $curitiba = Cidade::factory()->create(['grupo_id' => $u->grupo_id, 'descricao' => 'Curitiba', 'uf' => 'PR']);

        // "Centro" existe em 35 cidades da base real: sem o recorte, o select
        // de bairro ofereceria todos eles, indistinguíveis na lista.
        Bairro::create(['grupo_id' => $u->grupo_id, 'cidade_id' => $guarapuava->id, 'descricao' => 'Centro', 'ativo' => true]);
        Bairro::create(['grupo_id' => $u->grupo_id, 'cidade_id' => $curitiba->id, 'descricao' => 'Centro', 'ativo' => true]);

        $this->actingAs($u, 'sanctum')
            ->getJson('/api/admin/lookups/bairros?cidade_id='.$guarapuava->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Sem o parâmetro o comportamento antigo é preservado (lista tudo).
        $this->actingAs($u, 'sanctum')
            ->getJson('/api/admin/lookups/bairros')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_lookup_estatico_parentescos(): void
    {
        $u = $this->user();

        $this->actingAs($u, 'sanctum')
            ->getJson('/api/admin/lookups/parentescos')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'label']]]);
    }

    public function test_lookup_filtra_por_q(): void
    {
        $u = $this->user();
        Estado::firstOrCreate(['uf' => 'SP'], ['descricao' => 'São Paulo']);
        Estado::firstOrCreate(['uf' => 'RJ'], ['descricao' => 'Rio de Janeiro']);

        $resp = $this->actingAs($u, 'sanctum')->getJson('/api/admin/lookups/estados?q=paulo')->assertOk();
        $this->assertCount(1, $resp->json('data'));
        $this->assertSame('São Paulo', $resp->json('data.0.label'));
    }

    public function test_lookup_desconhecido_retorna_vazio(): void
    {
        $u = $this->user();
        $this->actingAs($u, 'sanctum')
            ->getJson('/api/admin/lookups/inexistente')
            ->assertOk()
            ->assertJsonPath('data', []);
    }
}
