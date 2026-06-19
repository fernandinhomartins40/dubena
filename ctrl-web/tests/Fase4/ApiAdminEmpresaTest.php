<?php

namespace Tests\Fase4;

use Tests\TestCase;
use App\Empresa;
use App\Empresaconfig;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;

/**
 * F3 — API admin de EMPRESA / CONFIG / GRUPOS.
 * CRUD, ativar empresa, config (sub-abas), senha mestra (Hash), grupos.
 */
class ApiAdminEmpresaTest extends TestCase
{
    use DatabaseTransactions;

    private function admin()
    {
        \Artisan::call('db:seed', ['--class' => '\DeployAdminSeeder', '--force' => true]);
        \Artisan::call('db:seed', ['--class' => '\RbacSeeder', '--force' => true]);
        return \App\User::where('email', env('ADMIN_SEED_EMAIL', 'admin'))->first();
    }

    /** Garante um setor para a empresa (config exige setor principal NOT NULL). */
    private function garantirSetor($admin): int
    {
        $existe = \DB::table('setors')->where('empresa_id', $admin->empresa_id)->value('id');
        if ($existe) {
            return $existe;
        }
        return \DB::table('setors')->insertGetId([
            'grupo_id' => optional($admin->empresa)->grupo_id ?? 1, 'empresa_id' => $admin->empresa_id,
            'cidade_id' => 1, 'bairro_id' => 1, 'descricao' => 'Setor Principal', 'numero' => '1',
            'cep' => '00000000', 'latitude' => 0, 'longitude' => 0, 'ativo' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_exige_autenticacao()
    {
        $this->getJson('/api/admin/empresas')->assertUnauthorized();
    }

    public function test_index_lista_empresas_com_flag_ativa()
    {
        $admin = $this->admin();
        $resp = $this->actingAs($admin)->getJson('/api/admin/empresas');
        $resp->assertOk()->assertJsonStructure(['data' => [['id', 'nome_informal', 'razao_social', 'ativa']]]);
        // a empresa do admin (seed id=1) deve aparecer como ativa
        $ativa = collect($resp->json('data'))->firstWhere('ativa', true);
        $this->assertNotNull($ativa);
    }

    public function test_show_traz_ficha_sem_segredos()
    {
        $admin = $this->admin();
        $resp = $this->actingAs($admin)->getJson("/api/admin/empresas/{$admin->empresa_id}");
        $resp->assertOk()->assertJsonPath('data.id', $admin->empresa_id);
        $this->assertArrayNotHasKey('nfesenhapfx', $resp->json('data'));
        $this->assertArrayNotHasKey('logoimg', $resp->json('data'));
    }

    public function test_update_empresa()
    {
        $admin = $this->admin();
        $this->actingAs($admin)->putJson("/api/admin/empresas/{$admin->empresa_id}", [
            'razao_social' => 'Empresa Atualizada SA', 'cidade_id' => 1, 'uf' => 'PR', 'ativo' => true,
        ])->assertOk();
        $this->assertEquals('Empresa Atualizada SA', Empresa::find($admin->empresa_id)->razao_social);
    }

    public function test_config_get_e_put()
    {
        $admin = $this->admin();
        $this->garantirSetor($admin);
        // sem config ainda → data null
        $this->actingAs($admin)->getJson("/api/admin/empresas/{$admin->empresa_id}/config")
            ->assertOk()->assertJsonPath('data', null);

        // grava config
        $this->actingAs($admin)->putJson("/api/admin/empresas/{$admin->empresa_id}/config", [
            'tempoentrega' => 30, 'permiteestoquenegativo' => true, 'maximoparcelas' => 6,
        ])->assertOk();

        $cfg = Empresaconfig::where('empresa_id', $admin->empresa_id)->first();
        $this->assertEquals(30, (int) $cfg->tempoentrega);
        $this->assertEquals(1, (int) $cfg->permiteestoquenegativo);

        // GET agora traz dados, sem segredos
        $resp = $this->actingAs($admin)->getJson("/api/admin/empresas/{$admin->empresa_id}/config");
        $resp->assertOk()->assertJsonPath('data.tempoentrega', 30);
        $this->assertArrayNotHasKey('senhamestre', $resp->json('data'));
    }

    public function test_senha_mestra_define_e_exige_atual()
    {
        $admin = $this->admin();
        $this->garantirSetor($admin);
        // cria config
        $this->actingAs($admin)->putJson("/api/admin/empresas/{$admin->empresa_id}/config", ['tempoentrega' => 10])->assertOk();

        // define (sem senha atual, pois não existe)
        $this->actingAs($admin)->putJson("/api/admin/empresas/{$admin->empresa_id}/config/senha-mestra", [
            'senha_nova' => 'segredo123',
        ])->assertOk();
        $cfg = Empresaconfig::where('empresa_id', $admin->empresa_id)->first();
        $this->assertTrue(Hash::check('segredo123', $cfg->senhamestre));

        // alterar com senha atual errada → 422
        $this->actingAs($admin)->putJson("/api/admin/empresas/{$admin->empresa_id}/config/senha-mestra", [
            'senha_atual' => 'errada', 'senha_nova' => 'nova123',
        ])->assertStatus(422);

        // alterar com senha atual correta → ok
        $this->actingAs($admin)->putJson("/api/admin/empresas/{$admin->empresa_id}/config/senha-mestra", [
            'senha_atual' => 'segredo123', 'senha_nova' => 'nova123',
        ])->assertOk();
        $this->assertTrue(Hash::check('nova123', Empresaconfig::where('empresa_id', $admin->empresa_id)->first()->senhamestre));
    }

    public function test_grupos_crud()
    {
        $admin = $this->admin();
        $gid = $this->actingAs($admin)->postJson('/api/admin/grupos', ['descricao' => 'Grupo Teste'])->assertCreated()->json('data.id');
        $this->actingAs($admin)->getJson('/api/admin/grupos')->assertOk()->assertJsonStructure(['data' => [['id', 'descricao', 'ativo']]]);
        $this->actingAs($admin)->putJson("/api/admin/grupos/$gid", ['descricao' => 'Grupo Editado'])->assertOk()->assertJsonPath('data.descricao', 'Grupo Editado');
        $this->actingAs($admin)->deleteJson("/api/admin/grupos/$gid")->assertOk();
    }

    public function test_lookups_contabeis()
    {
        $admin = $this->admin();
        $this->actingAs($admin)->getJson('/api/admin/lookups/planos-conta')->assertOk();
        $this->actingAs($admin)->getJson('/api/admin/lookups/centros-custo')->assertOk();
        $this->actingAs($admin)->getJson('/api/admin/lookups/setores')->assertOk();
    }
}
