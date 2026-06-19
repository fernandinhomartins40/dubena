<?php

namespace Tests\Fase4;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * F2 — API admin GEOGRÁFICO (Cidade/Bairro/Rua/Região).
 * CRUD, unicidade por escopo, cidade global, defaults da rua, FK amigável.
 */
class ApiAdminGeograficoTest extends TestCase
{
    use DatabaseTransactions;

    private function admin()
    {
        \Artisan::call('db:seed', ['--class' => '\DeployAdminSeeder', '--force' => true]);
        \Artisan::call('db:seed', ['--class' => '\RbacSeeder', '--force' => true]);
        return \App\User::where('email', env('ADMIN_SEED_EMAIL', 'admin'))->first();
    }

    public function test_exige_autenticacao()
    {
        $this->getJson('/api/admin/geo/cidades')->assertUnauthorized();
    }

    public function test_cidades_crud_e_unicidade()
    {
        $admin = $this->admin();
        $resp = $this->actingAs($admin)->postJson('/api/admin/geo/cidades', [
            'descricao' => 'Curitiba', 'uf' => 'PR', 'cod_ibge' => 4106902,
        ]);
        $resp->assertCreated();
        $id = $resp->json('data.id');

        // unicidade descricao+uf
        $this->actingAs($admin)->postJson('/api/admin/geo/cidades', ['descricao' => 'Curitiba', 'uf' => 'PR', 'cod_ibge' => 4106902])
            ->assertStatus(422);

        // valida obrigatórios
        $this->actingAs($admin)->postJson('/api/admin/geo/cidades', ['descricao' => 'X'])
            ->assertStatus(422)->assertJsonValidationErrors(['uf', 'cod_ibge']);

        // lista paginada
        $this->actingAs($admin)->getJson('/api/admin/geo/cidades?q=Curitiba')->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);

        // edita
        $this->actingAs($admin)->putJson("/api/admin/geo/cidades/$id", ['descricao' => 'Curitiba Editada', 'uf' => 'PR', 'cod_ibge' => 4106902])
            ->assertOk()->assertJsonPath('data.descricao', 'Curitiba Editada');

        $this->actingAs($admin)->deleteJson("/api/admin/geo/cidades/$id")->assertOk();
    }

    public function test_cidade_global_aparece_no_escopo()
    {
        $admin = $this->admin();
        // cidade global (grupo_id null) inserida direto
        $gid = \DB::table('cidades')->insertGetId([
            'descricao' => 'Cidade Global ZZZ', 'uf' => 'PR', 'cod_ibge' => 3550308, 'grupo_id' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $resp = $this->actingAs($admin)->getJson('/api/admin/geo/cidades?q=Cidade Global ZZZ');
        $resp->assertOk();
        $this->assertGreaterThanOrEqual(1, count($resp->json('data')));
        \DB::table('cidades')->where('id', $gid)->delete();
    }

    public function test_bairro_crud_e_unicidade()
    {
        $admin = $this->admin();
        $cidadeId = $this->actingAs($admin)->postJson('/api/admin/geo/cidades', ['descricao' => 'Cid Bairro', 'uf' => 'PR', 'cod_ibge' => 1])->json('data.id');

        $bid = $this->actingAs($admin)->postJson('/api/admin/geo/bairros', ['descricao' => 'Centro', 'cidade_id' => $cidadeId])->assertCreated()->json('data.id');
        // unicidade descricao+cidade_id
        $this->actingAs($admin)->postJson('/api/admin/geo/bairros', ['descricao' => 'Centro', 'cidade_id' => $cidadeId])->assertStatus(422);
        // lista filtrada por cidade
        $this->actingAs($admin)->getJson("/api/admin/geo/bairros?cidade_id=$cidadeId")->assertOk()->assertJsonPath('data.0.cidade_id', $cidadeId);
        $this->actingAs($admin)->deleteJson("/api/admin/geo/bairros/$bid")->assertOk();
    }

    public function test_rua_aplica_defaults_legado()
    {
        $admin = $this->admin();
        $cidadeId = $this->actingAs($admin)->postJson('/api/admin/geo/cidades', ['descricao' => 'Cid Rua', 'uf' => 'PR', 'cod_ibge' => 2])->json('data.id');

        $rid = $this->actingAs($admin)->postJson('/api/admin/geo/ruas', ['descricao' => 'Rua A', 'cidade_id' => $cidadeId])->assertCreated()->json('data.id');
        // defaults: importacaocep_id=-1, nfecompl='Rua'
        $rua = \App\Rua::find($rid);
        $this->assertEquals(-1, (int) $rua->importacaocep_id);
        $this->assertEquals('Rua', $rua->nfecompl);
        $this->assertEquals($admin->empresa_id, $rua->empresa_id);

        $this->actingAs($admin)->postJson('/api/admin/geo/ruas', ['descricao' => 'Rua A', 'cidade_id' => $cidadeId])->assertStatus(422);
        $this->actingAs($admin)->deleteJson("/api/admin/geo/ruas/$rid")->assertOk();
    }

    public function test_regiao_crud()
    {
        $admin = $this->admin();
        $rid = $this->actingAs($admin)->postJson('/api/admin/geo/regioes', ['descricao' => 'Zona Sul', 'ativo' => true])->assertCreated()->json('data.id');
        $this->actingAs($admin)->getJson('/api/admin/geo/regioes?q=Zona')->assertOk()->assertJsonStructure(['data', 'meta']);
        $this->actingAs($admin)->putJson("/api/admin/geo/regioes/$rid", ['descricao' => 'Zona Norte'])->assertOk()->assertJsonPath('data.descricao', 'Zona Norte');
        $this->actingAs($admin)->deleteJson("/api/admin/geo/regioes/$rid")->assertOk();
    }
}
