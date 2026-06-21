<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Grupo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * N1 — Empresas: CRUD escopado por grupo, troca de tenant ativo, config.
 */
class EmpresaTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioSuporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'support' => true,
        ]);

        return [$user, $empresa];
    }

    public function test_lista_apenas_empresas_do_grupo(): void
    {
        [$user, $empresa] = $this->usuarioSuporte();
        // outra empresa do mesmo grupo
        Empresa::factory()->create(['grupo_id' => $empresa->grupo_id]);
        // empresa de outro grupo (não deve aparecer)
        Empresa::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/empresas')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_marca_empresa_ativa_no_resource(): void
    {
        [$user, $empresa] = $this->usuarioSuporte();

        $resp = $this->actingAs($user, 'sanctum')->getJson('/api/admin/empresas')->assertOk();
        $ativa = collect($resp->json('data'))->firstWhere('id', $empresa->id);
        $this->assertTrue($ativa['ativa']);
    }

    public function test_cria_empresa_no_grupo_do_usuario(): void
    {
        [$user, $empresa] = $this->usuarioSuporte();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/empresas', ['razao_social' => 'Nova Revenda LTDA', 'uf' => 'SP'])
            ->assertCreated()
            ->assertJsonPath('data.razao_social', 'Nova Revenda LTDA')
            ->assertJsonPath('data.grupo_id', $empresa->grupo_id);
    }

    public function test_ativar_troca_tenant_e_persiste_no_usuario(): void
    {
        [$user, $empresa] = $this->usuarioSuporte();
        $outra = Empresa::factory()->create(['grupo_id' => $empresa->grupo_id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/empresas/{$outra->id}/ativar")
            ->assertOk()
            ->assertJsonPath('tenant.empresa_id', $outra->id);

        $this->assertEquals($outra->id, $user->fresh()->empresa_id);
    }

    public function test_nao_acessa_empresa_de_outro_grupo(): void
    {
        [$user] = $this->usuarioSuporte();
        $outraGrupo = Empresa::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/empresas/{$outraGrupo->id}")
            ->assertStatus(404);
    }

    public function test_config_cria_e_atualiza_com_dados_json(): void
    {
        [$user, $empresa] = $this->usuarioSuporte();

        // GET cria config vazia.
        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/empresas/{$empresa->id}/config")
            ->assertOk()
            ->assertJsonPath('data.empresa_id', $empresa->id);

        // PUT salva coluna estrutural + chave avulsa (vai pro JSON `dados`).
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/empresas/{$empresa->id}/config", [
                'tempoentrega' => 30,
                'validaatraso' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.tempoentrega', 30)
            ->assertJsonPath('data.validaatraso', true);

        $this->assertDatabaseHas('empresa_configs', [
            'empresa_id' => $empresa->id,
            'tempoentrega' => 30,
        ]);
    }

    public function test_senha_mestra_define_e_exige_atual_para_trocar(): void
    {
        [$user, $empresa] = $this->usuarioSuporte();

        // define pela primeira vez (sem senha atual)
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/empresas/{$empresa->id}/config/senha-mestra", ['senha_nova' => 'primeira'])
            ->assertOk();

        // trocar com senha atual errada → 422
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/empresas/{$empresa->id}/config/senha-mestra", [
                'senha_atual' => 'errada', 'senha_nova' => 'segunda',
            ])
            ->assertStatus(422);

        // a config nunca devolve o hash da senha
        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/empresas/{$empresa->id}/config")
            ->assertJsonPath('data.tem_senhamestre', true)
            ->assertJsonMissingPath('data.senha_mestra');
    }
}
