<?php

namespace Tests\Fase4;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * F7 — API admin FISCAL: Malha (CSTs/grupo/operações) + NF-e (lista) + SPED (preview).
 * A EMISSÃO (transmitir/cancelar) depende de SEFAZ homologação — não testada aqui (gate externo).
 */
class ApiAdminFiscalTest extends TestCase
{
    use DatabaseTransactions;

    private $admin;

    private function admin()
    {
        \Artisan::call('db:seed', ['--class' => '\DeployAdminSeeder', '--force' => true]);
        \Artisan::call('db:seed', ['--class' => '\RbacSeeder', '--force' => true]);
        return $this->admin = \App\User::where('email', env('ADMIN_SEED_EMAIL', 'admin'))->first();
    }

    public function test_exige_autenticacao()
    {
        $this->getJson('/api/admin/fiscal/malha/cst-icms')->assertUnauthorized();
    }

    public function test_malha_tipo_desconhecido_404()
    {
        $admin = $this->admin();
        $this->actingAs($admin)->getJson('/api/admin/fiscal/malha/inexistente')->assertNotFound();
    }

    public function test_malha_cst_icms_crud()
    {
        $admin = $this->admin();
        $id = $this->actingAs($admin)->postJson('/api/admin/fiscal/malha/cst-icms', ['codigo' => '00', 'descricao' => 'Tributada integralmente'])
            ->assertCreated()->json('data.id');
        $this->actingAs($admin)->getJson('/api/admin/fiscal/malha/cst-icms')->assertOk()->assertJsonStructure(['data' => [['id', 'descricao', 'codigo']]]);
        $this->actingAs($admin)->putJson("/api/admin/fiscal/malha/cst-icms/$id", ['codigo' => '00', 'descricao' => 'Tributada (editada)'])->assertOk()->assertJsonPath('data.descricao', 'Tributada (editada)');
        $this->actingAs($admin)->deleteJson("/api/admin/fiscal/malha/cst-icms/$id")->assertOk();
    }

    public function test_malha_grupo_fiscal_e_outros_csts()
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson('/api/admin/fiscal/malha/grupos-fiscais', ['descricao' => 'Grupo Padrão'])->assertCreated();
        $this->actingAs($admin)->postJson('/api/admin/fiscal/malha/cst-pis', ['codigo' => '01', 'descricao' => 'PIS 01'])->assertCreated();
        $this->actingAs($admin)->postJson('/api/admin/fiscal/malha/cst-cofins', ['codigo' => '01', 'descricao' => 'COFINS 01'])->assertCreated();
        $this->actingAs($admin)->getJson('/api/admin/fiscal/malha/grupos-fiscais')->assertOk()->assertJsonStructure(['data' => [['id', 'descricao']]]);
    }

    public function test_operacao_fiscal_crud()
    {
        $admin = $this->admin();
        $id = $this->actingAs($admin)->postJson('/api/admin/fiscal/operacoes', [
            'descricao' => 'Venda', 'cfop' => '5102', 'movimentaestoque' => true, 'movimentafinanceiro' => true,
        ])->assertCreated()->json('data.id');
        $this->actingAs($admin)->getJson('/api/admin/fiscal/operacoes')->assertOk()->assertJsonStructure(['data' => [['id', 'descricao', 'cfop']]]);
        $this->actingAs($admin)->deleteJson("/api/admin/fiscal/operacoes/$id")->assertOk();
    }

    public function test_nfe_lista()
    {
        $admin = $this->admin();
        $this->actingAs($admin)->getJson('/api/admin/fiscal/nfe')->assertOk()->assertJsonStructure(['data']);
    }

    public function test_sped_preview()
    {
        $admin = $this->admin();
        $this->actingAs($admin)->getJson('/api/admin/fiscal/sped?inicio=2026-06-01&fim=2026-06-30')
            ->assertOk()->assertJsonStructure(['data' => ['notas_emitidas', 'notas_recebidas', 'periodo']]);
    }
}
