<?php

namespace Tests\Fase4;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * F4 — API admin de Cadastros de Apoio (consolidado/genérico).
 * CRUD, unicidade por escopo, campos extras, FK amigável.
 */
class ApiAdminCadastroApoioTest extends TestCase
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
        $this->getJson('/api/admin/cadastros/segmentos')->assertUnauthorized();
    }

    public function test_tipo_desconhecido_404()
    {
        $admin = $this->admin();
        $this->actingAs($admin)->getJson('/api/admin/cadastros/inexistente')->assertNotFound();
    }

    public function test_segmentos_crud_e_unicidade()
    {
        $admin = $this->admin();
        $nome = 'Seg Teste ' . uniqid();
        $resp = $this->actingAs($admin)->postJson('/api/admin/cadastros/segmentos', ['descricao' => $nome]);
        $resp->assertCreated();
        $id = $resp->json('data.id');

        // unicidade (mesma descrição no escopo)
        $this->actingAs($admin)->postJson('/api/admin/cadastros/segmentos', ['descricao' => $nome])->assertStatus(422);
        // valida descricao
        $this->actingAs($admin)->postJson('/api/admin/cadastros/segmentos', [])->assertStatus(422)->assertJsonValidationErrors('descricao');
        // lista
        $this->actingAs($admin)->getJson('/api/admin/cadastros/segmentos')->assertOk()->assertJsonStructure(['data' => [['id', 'descricao', 'ativo']]]);
        // edita
        $this->actingAs($admin)->putJson("/api/admin/cadastros/segmentos/$id", ['descricao' => 'Seg Editado ' . uniqid()])->assertOk();
        // exclui
        $this->actingAs($admin)->deleteJson("/api/admin/cadastros/segmentos/$id")->assertOk();
    }

    public function test_tipos_pessoa_com_campo_extra()
    {
        $admin = $this->admin();
        $id = $this->actingAs($admin)->postJson('/api/admin/cadastros/tipos-pessoa', [
            'descricao' => 'Pessoa Jurídica', 'tipopessoacadastro' => 'J',
        ])->assertCreated()->json('data.id');
        $row = $this->actingAs($admin)->getJson('/api/admin/cadastros/tipos-pessoa')->json('data');
        $this->assertEquals('J', collect($row)->firstWhere('id', $id)['tipopessoacadastro']);
    }

    public function test_telefone_tipos_flag_bool()
    {
        $admin = $this->admin();
        $id = $this->actingAs($admin)->postJson('/api/admin/cadastros/telefone-tipos', [
            'descricao' => 'Celular ' . uniqid(), 'celular' => true,
        ])->assertCreated()->json('data.id');
        $row = collect($this->actingAs($admin)->getJson('/api/admin/cadastros/telefone-tipos')->json('data'))->firstWhere('id', $id);
        $this->assertEquals(1, (int) $row['celular']);
    }

    public function test_bancos_e_tipos_movimento()
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson('/api/admin/cadastros/bancos', ['descricao' => 'Banco do Brasil', 'codigo' => '001'])
            ->assertCreated()->assertJsonPath('data.codigo', '001');

        $this->actingAs($admin)->postJson('/api/admin/cadastros/tipos-movimento', [
            'descricao' => 'Recebimento', 'pagarreceber' => 'R', 'cheque' => true,
        ])->assertCreated()->assertJsonPath('data.pagarreceber', 'R');
    }
}
