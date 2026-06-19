<?php

namespace Tests\Fase4;

use Tests\TestCase;
use App\Produto;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * F1 — API admin do PRODUTO (consumida pelo SPA React).
 * Cobre CRUD, validações condicionais e regras de negócio (GLP soma 100/0,
 * origens soma 100%, não-inativar com saldo em estoque).
 */
class ApiAdminProdutoTest extends TestCase
{
    use DatabaseTransactions;

    private function admin()
    {
        \Artisan::call('db:seed', ['--class' => '\DeployAdminSeeder', '--force' => true]);
        \Artisan::call('db:seed', ['--class' => '\RbacSeeder', '--force' => true]);
        return \App\User::where('email', env('ADMIN_SEED_EMAIL', 'admin'))->first();
    }

    /** Cria classe + unidade no grupo do admin. $tipo: 'P' produto, 'G' GLP, 'V' vasilhame, 'R' ressarcimento. */
    private function apoio($admin, string $tipo = 'P'): array
    {
        $grupo = optional($admin->empresa)->grupo_id ?? 1;
        $classeId = \DB::table('produtoclasses')->insertGetId([
            'grupo_id' => $grupo, 'descricao' => 'Classe ' . $tipo, 'tipo' => $tipo, 'ativo' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $unidadeId = \DB::table('unidademedidas')->insertGetId([
            'grupo_id' => $grupo, 'descricao' => 'Unidade', 'sigla' => 'UN', 'ativo' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$classeId, $unidadeId];
    }

    public function test_index_exige_autenticacao()
    {
        $this->getJson('/api/admin/produtos')->assertUnauthorized();
    }

    public function test_index_lista_paginado()
    {
        $admin = $this->admin();
        $resp = $this->actingAs($admin)->getJson('/api/admin/produtos');
        $resp->assertOk()->assertJsonStructure([
            'data', 'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
    }

    public function test_store_cria_produto_simples()
    {
        $admin = $this->admin();
        [$classe, $unidade] = $this->apoio($admin);

        $resp = $this->actingAs($admin)->postJson('/api/admin/produtos', [
            'descricao' => 'Produto Teste',
            'produtoclasse_id' => $classe,
            'unidademedida_id' => $unidade,
            'vasilhameretornavel' => false,
            'precovenda' => 99.90,
            'ativo' => true,
        ]);
        $resp->assertCreated()->assertJsonPath('data.descricao', 'Produto Teste');
        $this->assertEquals(1, (int) $resp->json('data.ativo'));
    }

    public function test_store_valida_obrigatorios()
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson('/api/admin/produtos', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['descricao', 'produtoclasse_id', 'unidademedida_id', 'vasilhameretornavel']);
    }

    public function test_regra_glp_soma_diferente_de_100_falha()
    {
        $admin = $this->admin();
        [$classe, $unidade] = $this->apoio($admin);

        $resp = $this->actingAs($admin)->postJson('/api/admin/produtos', [
            'descricao' => 'GLP Inconsistente',
            'produtoclasse_id' => $classe,
            'unidademedida_id' => $unidade,
            'vasilhameretornavel' => false,
            'pgni' => 30, 'pgnn' => 30, 'pglp' => 20, // soma 80 → inválido
        ]);
        $resp->assertStatus(422);
        $this->assertStringContainsString('GLP', (string) $resp->json('message'));
    }

    public function test_regra_glp_soma_100_passa()
    {
        $admin = $this->admin();
        [$classe, $unidade] = $this->apoio($admin);

        $this->actingAs($admin)->postJson('/api/admin/produtos', [
            'descricao' => 'GLP OK',
            'produtoclasse_id' => $classe,
            'unidademedida_id' => $unidade,
            'vasilhameretornavel' => false,
            'pgni' => 40, 'pgnn' => 30, 'pglp' => 30, // soma 100 → ok
        ])->assertCreated();
    }

    public function test_origens_soma_diferente_de_100_falha()
    {
        $admin = $this->admin();
        [$classe, $unidade] = $this->apoio($admin);

        $resp = $this->actingAs($admin)->postJson('/api/admin/produtos', [
            'descricao' => 'Produto Origem Ruim',
            'produtoclasse_id' => $classe,
            'unidademedida_id' => $unidade,
            'vasilhameretornavel' => false,
            'origens' => [
                ['indimport' => 0, 'cuforig' => 41, 'porig' => 50],
                ['indimport' => 0, 'cuforig' => 35, 'porig' => 30], // soma 80 → inválido
            ],
        ]);
        $resp->assertStatus(422);
        $this->assertStringContainsString('Origem', (string) $resp->json('message'));
    }

    public function test_origens_soma_100_persiste()
    {
        $admin = $this->admin();
        [$classe, $unidade] = $this->apoio($admin);

        $id = $this->actingAs($admin)->postJson('/api/admin/produtos', [
            'descricao' => 'Produto Origem OK',
            'produtoclasse_id' => $classe,
            'unidademedida_id' => $unidade,
            'vasilhameretornavel' => false,
            'origens' => [
                ['indimport' => 0, 'cuforig' => 41, 'porig' => 60],
                ['indimport' => 1, 'cuforig' => 35, 'porig' => 40],
            ],
        ])->assertCreated()->json('data.id');

        $origens = $this->actingAs($admin)->getJson("/api/admin/produtos/$id/origens");
        $origens->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_nao_inativa_produto_com_saldo_em_estoque()
    {
        $admin = $this->admin();
        [$classe, $unidade] = $this->apoio($admin);
        $grupo = optional($admin->empresa)->grupo_id ?? 1;

        $id = $this->actingAs($admin)->postJson('/api/admin/produtos', [
            'descricao' => 'Produto Com Saldo',
            'produtoclasse_id' => $classe,
            'unidademedida_id' => $unidade,
            'vasilhameretornavel' => false,
            'ativo' => true,
        ])->json('data.id');

        // cria setor + saldo de estoque ≠ 0 (preenche NOT NULL do schema)
        $setorId = \DB::table('setors')->insertGetId([
            'grupo_id' => $grupo, 'empresa_id' => $admin->empresa_id,
            'cidade_id' => 1, 'bairro_id' => 1,
            'descricao' => 'Setor X', 'numero' => '1', 'cep' => '00000000',
            'latitude' => 0, 'longitude' => 0,
            'ativo' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        \DB::table('estoquesetors')->insert([
            'grupo_id' => $grupo, 'empresa_id' => $admin->empresa_id,
            'setor_id' => $setorId, 'produto_id' => $id,
            'quantidade' => 5, 'quantidademinima' => 0, 'quantidademaxima' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $resp = $this->actingAs($admin)->putJson("/api/admin/produtos/$id", [
            'descricao' => 'Produto Com Saldo',
            'produtoclasse_id' => $classe,
            'unidademedida_id' => $unidade,
            'vasilhameretornavel' => false,
            'ativo' => false, // tenta inativar com saldo
        ]);
        $resp->assertStatus(422);
        $this->assertStringContainsString('estoque', (string) $resp->json('message'));
    }

    public function test_update_produto_funciona()
    {
        $admin = $this->admin();
        [$classe, $unidade] = $this->apoio($admin);

        $id = $this->actingAs($admin)->postJson('/api/admin/produtos', [
            'descricao' => 'Antes', 'produtoclasse_id' => $classe,
            'unidademedida_id' => $unidade, 'vasilhameretornavel' => false,
        ])->json('data.id');

        $this->actingAs($admin)->putJson("/api/admin/produtos/$id", [
            'descricao' => 'Depois', 'produtoclasse_id' => $classe,
            'unidademedida_id' => $unidade, 'vasilhameretornavel' => false,
            'precovenda' => 12.34,
        ])->assertOk()->assertJsonPath('data.descricao', 'Depois');
    }

    public function test_destroy_produto()
    {
        $admin = $this->admin();
        [$classe, $unidade] = $this->apoio($admin);

        $id = $this->actingAs($admin)->postJson('/api/admin/produtos', [
            'descricao' => 'Para Excluir', 'produtoclasse_id' => $classe,
            'unidademedida_id' => $unidade, 'vasilhameretornavel' => false,
        ])->json('data.id');

        $this->actingAs($admin)->deleteJson("/api/admin/produtos/$id")->assertOk();
        $this->assertNull(Produto::find($id));
    }

    public function test_lookups_produto()
    {
        $admin = $this->admin();
        $this->apoio($admin);
        $this->actingAs($admin)->getJson('/api/admin/lookups/produto-classes')->assertOk()->assertJsonStructure([['id', 'label']]);
        $this->actingAs($admin)->getJson('/api/admin/lookups/unidades')->assertOk()->assertJsonStructure([['id', 'label']]);
        $this->actingAs($admin)->getJson('/api/admin/lookups/estados')->assertOk()->assertJsonStructure([['id', 'label', 'uf']]);
        $this->actingAs($admin)->getJson('/api/admin/lookups/tipo-glp')->assertOk()->assertJsonStructure([['id', 'label']]);
    }

    // ---- Visão nova / reorganização (DS) ----

    public function test_estoque_por_setor_e_giro()
    {
        $admin = $this->admin();
        [$classe, $unidade] = $this->apoio($admin);
        $grupo = optional($admin->empresa)->grupo_id ?? 1;
        $id = $this->actingAs($admin)->postJson('/api/admin/produtos', [
            'descricao' => 'Com Estoque', 'produtoclasse_id' => $classe,
            'unidademedida_id' => $unidade, 'vasilhameretornavel' => false, 'diasgiro' => 7,
        ])->json('data.id');

        $setorId = \DB::table('setors')->insertGetId([
            'grupo_id' => $grupo, 'empresa_id' => $admin->empresa_id, 'cidade_id' => 1, 'bairro_id' => 1,
            'descricao' => 'Setor Estq', 'numero' => '1', 'cep' => '00000000', 'latitude' => 0, 'longitude' => 0,
            'ativo' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        \DB::table('estoquesetors')->insert([
            'grupo_id' => $grupo, 'empresa_id' => $admin->empresa_id, 'setor_id' => $setorId, 'produto_id' => $id,
            'quantidade' => 12, 'quantidademinima' => 2, 'quantidademaxima' => 50, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $resp = $this->actingAs($admin)->getJson("/api/admin/produtos/$id/estoque");
        $resp->assertOk()->assertJsonPath('data.diasgiro', 7)->assertJsonPath('data.total', 12);
        $this->assertCount(1, $resp->json('data.setores'));
    }

    public function test_config_classes_crud()
    {
        $admin = $this->admin();
        $resp = $this->actingAs($admin)->postJson('/api/admin/produto-config/classes', ['descricao' => 'Classe Nova', 'tipo' => 'P']);
        $resp->assertCreated();
        $cid = $resp->json('data.id');
        // unicidade
        $this->actingAs($admin)->postJson('/api/admin/produto-config/classes', ['descricao' => 'Classe Nova'])->assertStatus(422);
        $this->actingAs($admin)->getJson('/api/admin/produto-config/classes')->assertOk()->assertJsonStructure(['data' => [['id', 'descricao', 'tipo', 'ativo']]]);
        $this->actingAs($admin)->putJson("/api/admin/produto-config/classes/$cid", ['descricao' => 'Classe Editada'])->assertOk()->assertJsonPath('data.descricao', 'Classe Editada');
        $this->actingAs($admin)->deleteJson("/api/admin/produto-config/classes/$cid")->assertOk();
    }

    public function test_config_unidades_crud()
    {
        $admin = $this->admin();
        $resp = $this->actingAs($admin)->postJson('/api/admin/produto-config/unidades', ['descricao' => 'Caixa', 'sigla' => 'CX']);
        $resp->assertCreated();
        $uid = $resp->json('data.id');
        $this->actingAs($admin)->getJson('/api/admin/produto-config/unidades')->assertOk()->assertJsonStructure(['data' => [['id', 'descricao', 'sigla', 'ativo']]]);
        $this->actingAs($admin)->deleteJson("/api/admin/produto-config/unidades/$uid")->assertOk();
    }

    public function test_precos_preview_e_aplicar()
    {
        $admin = $this->admin();
        [$classe, $unidade] = $this->apoio($admin);
        $id = $this->actingAs($admin)->postJson('/api/admin/produtos', [
            'descricao' => 'Preço Base', 'produtoclasse_id' => $classe,
            'unidademedida_id' => $unidade, 'vasilhameretornavel' => false, 'precovenda' => 100,
        ])->json('data.id');

        // preview não altera; +10% → 110
        $prev = $this->actingAs($admin)->getJson('/api/admin/produtos-precos/preview?tipo=percentual&valor=10');
        $prev->assertOk();
        $item = collect($prev->json('data'))->firstWhere('id', $id);
        $this->assertEquals(110, $item['novo']);
        $this->assertEquals(100, (float) Produto::find($id)->precovenda); // não gravou

        // aplica
        $this->actingAs($admin)->putJson('/api/admin/produtos-precos/aplicar', ['tipo' => 'percentual', 'valor' => 10])->assertOk();
        $this->assertEquals(110, (float) Produto::find($id)->precovenda);
    }
}
