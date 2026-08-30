<?php

namespace Tests\Feature;

use App\Models\ConfigGlobal;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F01 — config global por grupo (RT/CSRT, SMTP, SAT, Google Maps). CRUD + RBAC +
 * tratamento de segredos (não voltam no GET; vazio não apaga o salvo).
 */
class ConfigGlobalTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Empresa} */
    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        return [$user, $empresa];
    }

    public function test_show_cria_config_vazia_do_grupo(): void
    {
        [$user, $empresa] = $this->suporte();

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/config-global')
            ->assertOk()
            ->assertJsonPath('data.rt_csrt_definido', false);

        $this->assertSame(1, ConfigGlobal::withoutGrupo()->where('grupo_id', $empresa->grupo_id)->count());
    }

    public function test_update_salva_e_segredo_nao_volta(): void
    {
        [$user] = $this->suporte();

        $this->actingAs($user, 'sanctum')->putJson('/api/admin/config-global', [
            'rt_cnpj' => '12345678000199', 'rt_contato' => 'Fulano', 'rt_id_csrt' => '01',
            'rt_csrt' => 'segredo-csrt', 'email_host' => 'smtp.x.com', 'email_porta' => 587,
            'google_maps_key' => 'KEY123',
        ])->assertOk()
            ->assertJsonPath('data.rt_cnpj', '12345678000199')
            ->assertJsonPath('data.rt_csrt_definido', true)
            ->assertJsonPath('data.google_maps_key', 'KEY123');

        // O GET não devolve o valor do segredo, só a flag.
        $resp = $this->actingAs($user, 'sanctum')->getJson('/api/admin/config-global')->assertOk();
        $this->assertArrayNotHasKey('rt_csrt', $resp->json('data'));
        $this->assertTrue($resp->json('data.rt_csrt_definido'));
    }

    public function test_update_com_segredo_vazio_preserva_valor(): void
    {
        [$user, $empresa] = $this->suporte();

        $this->actingAs($user, 'sanctum')->putJson('/api/admin/config-global', ['rt_csrt' => 'csrt-original'])->assertOk();
        // Segunda atualização sem mandar o csrt → preserva.
        $this->actingAs($user, 'sanctum')->putJson('/api/admin/config-global', ['rt_contato' => 'Outro'])->assertOk();

        $config = ConfigGlobal::withoutGrupo()->where('grupo_id', $empresa->grupo_id)->first();
        $this->assertSame('csrt-original', $config->rt_csrt); // decriptado pelo cast
        $this->assertSame('Outro', $config->rt_contato);
    }

    public function test_isola_config_entre_grupos(): void
    {
        [$userA] = $this->suporte();
        [$userB, $empresaB] = $this->suporte();

        $this->actingAs($userA, 'sanctum')->putJson('/api/admin/config-global', ['rt_contato' => 'Grupo A'])->assertOk();

        // B (outro grupo) não enxerga o contato de A.
        $this->actingAs($userB, 'sanctum')->getJson('/api/admin/config-global')
            ->assertOk()->assertJsonPath('data.rt_contato', null);
    }

    public function test_exige_permissao(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->semPapel()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/config-global')->assertForbidden();
    }
}
