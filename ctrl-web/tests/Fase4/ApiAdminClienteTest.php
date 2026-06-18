<?php

namespace Tests\Fase4;

use Tests\TestCase;
use App\Cliente;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * S2 — API admin do Cliente (consumida pelo SPA React). Auth/escopo/CRUD.
 */
class ApiAdminClienteTest extends TestCase
{
    use DatabaseTransactions;

    private function admin()
    {
        \Artisan::call('db:seed', ['--class' => '\DeployAdminSeeder', '--force' => true]);
        \Artisan::call('db:seed', ['--class' => '\RbacSeeder', '--force' => true]);
        return \App\User::where('email', env('ADMIN_SEED_EMAIL', 'admin'))->first();
    }

    public function test_index_exige_autenticacao()
    {
        $this->getJson('/api/admin/clientes')->assertUnauthorized();
    }

    public function test_index_lista_paginado()
    {
        $admin = $this->admin();
        $resp = $this->actingAs($admin)->getJson('/api/admin/clientes');
        $resp->assertOk()->assertJsonStructure([
            'data', 'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
    }

    public function test_busca_por_nome()
    {
        $admin = $this->admin();
        // cria um cliente identificável na empresa do admin
        $c = new Cliente();
        $c->grupo_id = optional($admin->empresa)->grupo_id ?? 1;
        $c->empresa_id = $admin->empresa_id;
        $c->nome = 'ZZZ Cliente Busca Teste';
        $c->numero = '1';
        $c->cidade_id = \App\Cidade::min('id') ?? 1;
        $c->observacoes = '';
        $c->conveniolimite = 0; $c->latitude = 0; $c->longitude = 0; $c->locationtype = 'APPROXIMATE';
        $c->cliente = 1; $c->fornecedor = 0; $c->transportador = 0; $c->nfemite = 0; $c->convenio = 0;
        $c->consumidor_final = 0; $c->simples = 0; $c->ativo = 1;
        $c->save();

        $resp = $this->actingAs($admin)->getJson('/api/admin/clientes?q=ZZZ Cliente Busca');
        $resp->assertOk();
        $this->assertGreaterThanOrEqual(1, count($resp->json('data')));
    }

    public function test_store_cria_cliente()
    {
        $admin = $this->admin();
        $resp = $this->actingAs($admin)->postJson('/api/admin/clientes', [
            'nome' => 'Cliente Novo via API',
            'numero' => '10',
            'cidade_id' => \App\Cidade::min('id') ?? 1,
        ]);
        $resp->assertCreated()->assertJsonPath('data.nome', 'Cliente Novo via API');
    }

    public function test_store_valida_nome()
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson('/api/admin/clientes', ['numero' => '1', 'cidade_id' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('nome');
    }
}
