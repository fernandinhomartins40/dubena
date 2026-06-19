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

    public function test_store_cria_cliente_completo_com_flags_e_fiscal()
    {
        $admin = $this->admin();
        $resp = $this->actingAs($admin)->postJson('/api/admin/clientes', [
            'nome' => 'Cliente Completo PJ',
            'fantasia' => 'Fantasia X',
            'numero' => '99',
            'cidade_id' => \App\Cidade::min('id') ?? 1,
            'cnpj' => '12345678000190',
            'inscricao_estadual' => '123456',
            'indicador_ie' => 1,
            'cliente' => true, 'fornecedor' => true, 'ativo' => true, 'nfemite' => true,
            'observacoes' => 'obs teste',
        ]);
        $resp->assertCreated();
        $id = $resp->json('data.id');
        // confere persistência dos flags (smallint) e fiscal
        $show = $this->actingAs($admin)->getJson("/api/admin/clientes/$id");
        $show->assertOk()
            ->assertJsonPath('data.fantasia', 'Fantasia X')
            ->assertJsonPath('data.cnpj', '12345678000190');
        $this->assertEquals(1, (int) $show->json('data.fornecedor'));
        $this->assertEquals(1, (int) $show->json('data.nfemite'));
    }

    public function test_update_cliente_funciona_com_revisionable()
    {
        // Garante que editar cliente (entidade auditada) não quebra com o
        // RevisionsTraitService (Event::fire removido no L12 → dispatch).
        $admin = $this->admin();
        $cidadeId = \App\Cidade::min('id') ?? 1;
        $id = $this->actingAs($admin)->postJson('/api/admin/clientes', ['nome' => 'Cli Edit', 'numero' => '1', 'cidade_id' => $cidadeId])->json('data.id');

        $this->actingAs($admin)->putJson("/api/admin/clientes/$id", [
            'nome' => 'Cli Editado', 'numero' => '2', 'cidade_id' => $cidadeId, 'observacoes' => 'mudou',
        ])->assertOk()->assertJsonPath('data.nome', 'Cli Editado');
    }

    public function test_interacoes_e_convenio_subrecursos()
    {
        $admin = $this->admin();
        $cidadeId = \App\Cidade::min('id') ?? 1;
        $cli = $this->actingAs($admin)->postJson('/api/admin/clientes', ['nome' => 'Cli Sub', 'numero' => '1', 'cidade_id' => $cidadeId])->json('data.id');

        // interação (CRM) — cria tipo/situação se faltar
        $grupo = optional($admin->empresa)->grupo_id ?? 1;
        $tipoId = \DB::table('clientecontatotipos')->where('grupo_id', $grupo)->value('id')
            ?? \DB::table('clientecontatotipos')->insertGetId(['grupo_id' => $grupo, 'descricao' => 'Ligação', 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $sitId = \DB::table('clientecontatosituacaos')->where('grupo_id', $grupo)->value('id')
            ?? \DB::table('clientecontatosituacaos')->insertGetId(['grupo_id' => $grupo, 'descricao' => 'Aberto', 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($admin)->postJson("/api/admin/clientes/$cli/interacoes", [
            'tipo_id' => $tipoId, 'situacao_id' => $sitId, 'descricao' => 'Contato inicial',
        ])->assertCreated();
        $this->actingAs($admin)->getJson("/api/admin/clientes/$cli/interacoes")->assertOk()->assertJsonCount(1, 'data');

        // convênio (empresa conveniada)
        $this->actingAs($admin)->putJson("/api/admin/clientes/$cli/convenio", [
            'convenioativo' => true, 'limitecompra' => 500, 'diafechamento' => 10, 'diavencimento' => 20,
        ])->assertOk();
        $conv = $this->actingAs($admin)->getJson("/api/admin/clientes/$cli/convenio");
        $conv->assertOk()->assertJsonPath('convenioativo', 1);
        $this->assertEquals(500, (int) $conv->json('convenio.limitecompra'));
    }

    public function test_store_valida_nome()
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson('/api/admin/clientes', ['numero' => '1', 'cidade_id' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('nome');
    }

    public function test_lookup_cidades()
    {
        $admin = $this->admin();
        $this->actingAs($admin)->getJson('/api/admin/lookups/cidades')
            ->assertOk()->assertJsonStructure([['id', 'label']]);
    }

    public function test_telefones_ciclo_completo()
    {
        $admin = $this->admin();
        $cidadeId = \App\Cidade::min('id') ?? 1;

        // cria cliente
        $cli = $this->actingAs($admin)->postJson('/api/admin/clientes', [
            'nome' => 'Cliente Tel', 'numero' => '1', 'cidade_id' => $cidadeId,
        ])->json('data.id');

        // tipo de telefone (cria um se não houver no grupo)
        $tipoId = \App\Telefonetipo::query()->value('id');
        if (! $tipoId) {
            $tipoId = \DB::table('telefonetipos')->insertGetId([
                'grupo_id' => optional($admin->empresa)->grupo_id ?? 1,
                'descricao' => 'Celular', 'ativo' => 1, 'celular' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // adiciona telefone
        $this->actingAs($admin)->postJson("/api/admin/clientes/$cli/telefones", [
            'telefone' => '41999990000', 'whatsapp' => true, 'telefonetipo_id' => $tipoId,
        ])->assertCreated();

        // lista
        $tels = $this->actingAs($admin)->getJson("/api/admin/clientes/$cli/telefones");
        $tels->assertOk();
        $this->assertCount(1, $tels->json('data'));

        // remove
        $telId = $tels->json('data.0.id');
        $this->actingAs($admin)->deleteJson("/api/admin/clientes/$cli/telefones/$telId")->assertOk();
        $this->actingAs($admin)->getJson("/api/admin/clientes/$cli/telefones")
            ->assertOk()->assertJsonCount(0, 'data');
    }
}
