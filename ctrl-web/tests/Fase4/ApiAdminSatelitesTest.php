<?php

namespace Tests\Fase4;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * F8 — API admin SATÉLITES: RH (Colaboradores) + Frota (Veículos) + Vale-Gás
 * + Relatórios/Monitoramento/Integrações (status).
 */
class ApiAdminSatelitesTest extends TestCase
{
    use DatabaseTransactions;

    private $admin;
    private $grupo;
    private $empresaId;

    private function preparar()
    {
        \Artisan::call('db:seed', ['--class' => '\DeployAdminSeeder', '--force' => true]);
        \Artisan::call('db:seed', ['--class' => '\RbacSeeder', '--force' => true]);
        $this->admin = \App\User::where('email', env('ADMIN_SEED_EMAIL', 'admin'))->first();
        $this->grupo = optional($this->admin->empresa)->grupo_id ?? 1;
        $this->empresaId = $this->admin->empresa_id;
    }

    public function test_exige_autenticacao()
    {
        $this->getJson('/api/admin/colaboradores')->assertUnauthorized();
    }

    // ---- RH ----
    public function test_colaborador_crud_e_familia()
    {
        $this->preparar();
        $id = $this->actingAs($this->admin)->postJson('/api/admin/colaboradores', [
            'nome' => 'João Motorista', 'numero' => '10', 'cidade_id' => 1, 'bairro_id' => 1,
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->admin)->getJson('/api/admin/colaboradores')->assertOk()->assertJsonStructure(['data', 'meta']);
        $this->actingAs($this->admin)->getJson("/api/admin/colaboradores/$id")->assertOk()->assertJsonPath('data.nome', 'João Motorista');

        // família (scaffold reescrito)
        $par = \DB::table('parentescos')->insertGetId(['grupo_id' => $this->grupo, 'descricao' => 'Filho', 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($this->admin)->postJson("/api/admin/colaboradores/$id/familia", ['nome' => 'Maria', 'parentesco_id' => $par])->assertCreated();
        $this->actingAs($this->admin)->getJson("/api/admin/colaboradores/$id/familia")->assertOk()->assertJsonCount(1, 'data');

        // recessos/comissões respondem (vazios)
        $this->actingAs($this->admin)->getJson("/api/admin/colaboradores/$id/recessos")->assertOk()->assertJsonStructure(['data']);
        $this->actingAs($this->admin)->getJson("/api/admin/colaboradores/$id/comissoes")->assertOk()->assertJsonStructure(['data']);

        $this->actingAs($this->admin)->deleteJson("/api/admin/colaboradores/$id")->assertOk();
    }

    public function test_colaborador_valida_obrigatorios()
    {
        $this->preparar();
        $this->actingAs($this->admin)->postJson('/api/admin/colaboradores', [])
            ->assertStatus(422)->assertJsonValidationErrors(['nome', 'numero', 'cidade_id', 'bairro_id']);
    }

    // ---- Frota ----
    public function test_veiculo_crud_e_timeline()
    {
        $this->preparar();
        $tipo = \DB::table('veiculotipos')->insertGetId(['grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'descricao' => 'Caminhão', 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $comb = \DB::table('tipocombustivels')->insertGetId(['grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'descricao' => 'Diesel', 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $id = $this->actingAs($this->admin)->postJson('/api/admin/veiculos', [
            'placa' => 'ABC1D23', 'descricao' => 'Caminhão 1', 'veiculotipo_id' => $tipo, 'tipocombustivel_id' => $comb, 'kmatual' => 1000,
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->admin)->getJson('/api/admin/veiculos')->assertOk()->assertJsonStructure(['data' => [['id', 'placa', 'descricao']]]);
        $this->actingAs($this->admin)->getJson("/api/admin/veiculos/$id")->assertOk()->assertJsonPath('data.descricao', 'Caminhão 1');
        $this->actingAs($this->admin)->getJson("/api/admin/veiculos/$id/abastecimentos")->assertOk()->assertJsonStructure(['data']);
        $this->actingAs($this->admin)->getJson("/api/admin/veiculos/$id/trocas-oleo")->assertOk()->assertJsonStructure(['data']);
        $this->actingAs($this->admin)->getJson("/api/admin/veiculos/$id/pneus")->assertOk()->assertJsonStructure(['data']);
        $this->actingAs($this->admin)->deleteJson("/api/admin/veiculos/$id")->assertOk();
    }

    // ---- Vale-Gás ----
    public function test_vale_gas_lista_e_baixar()
    {
        $this->preparar();
        $cli = \DB::table('clientes')->insertGetId([
            'grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'nome' => 'Cli VG', 'numero' => '1', 'cidade_id' => 1,
            'observacoes' => '', 'conveniolimite' => 0, 'latitude' => 0, 'longitude' => 0, 'locationtype' => 'APPROXIMATE',
            'cliente' => 1, 'fornecedor' => 0, 'transportador' => 0, 'nfemite' => 0, 'convenio' => 0, 'consumidor_final' => 0, 'simples' => 0, 'ativo' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $classe = \DB::table('produtoclasses')->insertGetId(['grupo_id' => $this->grupo, 'descricao' => 'C', 'tipo' => 'G', 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $unidade = \DB::table('unidademedidas')->insertGetId(['grupo_id' => $this->grupo, 'descricao' => 'UN', 'sigla' => 'UN', 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $prod = \DB::table('produtos')->insertGetId([
            'grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'produtoclasse_id' => $classe, 'unidademedida_id' => $unidade,
            'descricao' => 'GLP', 'vasilhameretornavel' => false, 'ativo' => 1, 'nfepermite' => 0,
            'customedio' => 0, 'custofrete' => 0, 'precovenda' => 0, 'precovendaminimo' => 0, 'pesoliquido' => 0, 'pesobruto' => 0,
            'observacao' => '', 'ean' => '', 'ncm' => '', 'especie' => '', 'marca' => '', 'nfedescricaofiscal' => '',
            'nfetipoitem' => 0, 'nfeextipi' => '', 'nfecodgen' => 0, 'nfecodlst' => 0, 'nfenatrec' => '',
            'nfecodenquadramentoipi' => 0, 'nfecprodanp' => '', 'nfeqbcprod' => 0, 'nfevaliqprod' => 0, 'nfevcide' => 0,
            'pGNi' => 0, 'pGNn' => 0, 'pGLP' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $venda = \DB::table('valegasvendas')->insertGetId([
            'grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'cliente_id' => $cli, 'produto_id' => $prod,
            'datavenda' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $sit = \DB::table('valegassituacaos')->insertGetId(['descricao' => 'GERADO', 'created_at' => now(), 'updated_at' => now()]);
        $sitBaixa = \DB::table('valegassituacaos')->insertGetId(['descricao' => 'BAIXADO', 'created_at' => now(), 'updated_at' => now()]);
        \DB::table('valegas')->insert([
            'grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'cliente_id' => $cli, 'produto_id' => $prod,
            'valegasvenda_id' => $venda, 'valegassituacao_id' => $sit,
            'codigo' => 'VG001', 'datageracao' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)->getJson('/api/admin/vale-gas?q=VG001')->assertOk()->assertJsonStructure(['data' => [['id', 'codigo', 'situacao']]]);
        $this->actingAs($this->admin)->getJson('/api/admin/vale-gas/situacoes')->assertOk()->assertJsonStructure(['data' => [['id', 'descricao']]]);

        // baixar pelo código
        $this->actingAs($this->admin)->postJson('/api/admin/vale-gas/baixar', ['codigo' => 'VG001', 'situacao_id' => $sitBaixa])->assertOk();
        $this->assertEquals($sitBaixa, \DB::table('valegas')->where('codigo', 'VG001')->value('valegassituacao_id'));

        // código inexistente → 404
        $this->actingAs($this->admin)->postJson('/api/admin/vale-gas/baixar', ['codigo' => 'NAOEXISTE', 'situacao_id' => $sitBaixa])->assertStatus(404);
    }

    // ---- Relatórios / Monitoramento / Integrações ----
    public function test_satelites_status()
    {
        $this->preparar();
        $this->actingAs($this->admin)->getJson('/api/admin/satelites/relatorios')->assertOk()->assertJsonStructure(['data' => [['categoria', 'relatorios']]]);
        $this->actingAs($this->admin)->getJson('/api/admin/satelites/monitoramento')->assertOk()->assertJsonStructure(['data' => ['disponivel']]);
        $this->actingAs($this->admin)->getJson('/api/admin/satelites/integracoes')->assertOk()->assertJsonStructure(['data' => ['pix', 'email_smtp', 'google_maps']]);
    }
}
