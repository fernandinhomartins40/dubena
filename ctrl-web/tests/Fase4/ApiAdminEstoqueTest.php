<?php

namespace Tests\Fase4;

use Tests\TestCase;
use App\Estoquesetor;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * F5 — API admin de ESTOQUE (6 fluxos). Usa o motor EstoqueProcessor
 * (baseline em EstoqueProcessorBaselineTest). Cobre acerto, transferência,
 * requisição, inventário, físico (efetivar), fechamento.
 */
class ApiAdminEstoqueTest extends TestCase
{
    use DatabaseTransactions;

    private $admin;
    private $grupo;
    private $empresaId;
    private $setorA;
    private $setorB;
    private $produtoId;

    private function preparar()
    {
        \Artisan::call('db:seed', ['--class' => '\DeployAdminSeeder', '--force' => true]);
        \Artisan::call('db:seed', ['--class' => '\RbacSeeder', '--force' => true]);
        $this->admin = \App\User::where('email', env('ADMIN_SEED_EMAIL', 'admin'))->first();
        $this->grupo = optional($this->admin->empresa)->grupo_id ?? 1;
        $this->empresaId = $this->admin->empresa_id;

        $this->setorA = $this->setor('Setor A');
        $this->setorB = $this->setor('Setor B');
        \App\Empresaconfig::updateOrCreate(
            ['empresa_id' => $this->empresaId, 'grupo_id' => $this->grupo],
            ['permiteestoquenegativo' => 1, 'diastrabalhadosemana' => 6, 'setorprincipal_id' => $this->setorA]
        );

        $classe = \DB::table('produtoclasses')->insertGetId(['grupo_id' => $this->grupo, 'descricao' => 'C', 'tipo' => 'P', 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $unidade = \DB::table('unidademedidas')->insertGetId(['grupo_id' => $this->grupo, 'descricao' => 'UN', 'sigla' => 'UN', 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->produtoId = \DB::table('produtos')->insertGetId([
            'grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'produtoclasse_id' => $classe, 'unidademedida_id' => $unidade,
            'descricao' => 'Prod Estq', 'vasilhameretornavel' => false, 'ativo' => 1, 'nfepermite' => 1,
            'customedio' => 0, 'custofrete' => 0, 'precovenda' => 10, 'precovendaminimo' => 0, 'pesoliquido' => 0, 'pesobruto' => 0,
            'observacao' => '', 'ean' => '', 'ncm' => '', 'especie' => '', 'marca' => '', 'nfedescricaofiscal' => '',
            'nfetipoitem' => 0, 'nfeextipi' => '', 'nfecodgen' => 0, 'nfecodlst' => 0, 'nfenatrec' => '',
            'nfecodenquadramentoipi' => 0, 'nfecprodanp' => '', 'nfeqbcprod' => 0, 'nfevaliqprod' => 0, 'nfevcide' => 0,
            'pGNi' => 0, 'pGNn' => 0, 'pGLP' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function setor(string $nome): int
    {
        return \DB::table('setors')->insertGetId([
            'grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'cidade_id' => 1, 'bairro_id' => 1,
            'descricao' => $nome, 'numero' => '1', 'cep' => '00000000', 'latitude' => 0, 'longitude' => 0,
            'ativo' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function saldo(int $setor): float
    {
        return (float) Estoquesetor::where(['produto_id' => $this->produtoId, 'setor_id' => $setor])->value('quantidade');
    }

    public function test_exige_autenticacao()
    {
        $this->getJson('/api/admin/estoque/saldos')->assertUnauthorized();
    }

    public function test_acerto_entrada_e_saldos()
    {
        $this->preparar();
        $this->actingAs($this->admin)->postJson('/api/admin/estoque/acerto', [
            'setor_id' => $this->setorA, 'produto_id' => $this->produtoId,
            'movimentacao' => 'ENTRADA', 'quantidade' => 25, 'observacao' => 'carga inicial',
        ])->assertOk();
        $this->assertEquals(25, $this->saldo($this->setorA));

        $resp = $this->actingAs($this->admin)->getJson("/api/admin/estoque/saldos?setor_id={$this->setorA}");
        $resp->assertOk()->assertJsonStructure(['data' => [['setor', 'produto', 'quantidade']]]);
    }

    public function test_transferencia_move_entre_setores()
    {
        $this->preparar();
        // carga no A
        $this->actingAs($this->admin)->postJson('/api/admin/estoque/acerto', [
            'setor_id' => $this->setorA, 'produto_id' => $this->produtoId, 'movimentacao' => 'ENTRADA', 'quantidade' => 30, 'observacao' => 'carga',
        ])->assertOk();

        $this->actingAs($this->admin)->postJson('/api/admin/estoque/transferencias', [
            'origemsetor_id' => $this->setorA, 'destinosetor_id' => $this->setorB, 'observacoes' => 'transf',
            'itens' => [['produto_id' => $this->produtoId, 'quantidade' => 12]],
        ])->assertCreated();

        $this->assertEquals(18, $this->saldo($this->setorA));
        $this->assertEquals(12, $this->saldo($this->setorB));
    }

    public function test_transferencia_mesmo_setor_invalida()
    {
        $this->preparar();
        $this->actingAs($this->admin)->postJson('/api/admin/estoque/transferencias', [
            'origemsetor_id' => $this->setorA, 'destinosetor_id' => $this->setorA,
            'itens' => [['produto_id' => $this->produtoId, 'quantidade' => 1]],
        ])->assertStatus(422);
    }

    public function test_requisicao_movimenta()
    {
        $this->preparar();
        $this->actingAs($this->admin)->postJson('/api/admin/estoque/requisicoes', [
            'observacoes' => 'req',
            'itens' => [['produto_id' => $this->produtoId, 'setor_id' => $this->setorA, 'quantidade' => 7, 'entradasaida' => 'ENTRADA']],
        ])->assertCreated();
        $this->assertEquals(7, $this->saldo($this->setorA));
    }

    public function test_inventario_calcula_valor()
    {
        $this->preparar();
        $resp = $this->actingAs($this->admin)->postJson('/api/admin/estoque/inventarios', [
            'datainventario' => '2026-06-30', 'mesentrega' => '2026-06-01',
            'itens' => [['produto_id' => $this->produtoId, 'quantidade' => 10, 'valorunitario' => 5]],
        ])->assertCreated();
        $this->assertEquals(50, (float) $resp->json('data.valorinventario'));
    }

    public function test_fisico_registra_e_efetiva()
    {
        $this->preparar();
        // saldo sistema = 20
        $this->actingAs($this->admin)->postJson('/api/admin/estoque/acerto', [
            'setor_id' => $this->setorA, 'produto_id' => $this->produtoId, 'movimentacao' => 'ENTRADA', 'quantidade' => 20, 'observacao' => 'c',
        ])->assertOk();

        // contagem física = 15 (diferença 5 → SAÍDA ao efetivar)
        $id = $this->actingAs($this->admin)->postJson('/api/admin/estoque/fisico', [
            'datacompetencia' => now()->toDateTimeString(),
            'itens' => [['setor_id' => $this->setorA, 'produto_id' => $this->produtoId, 'quantidadesistema' => 20, 'quantidadefisica' => 15]],
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->admin)->postJson("/api/admin/estoque/fisico/$id/efetivar")->assertOk();
        $this->assertEquals(15, $this->saldo($this->setorA));
    }

    public function test_fechamento_e_abertura()
    {
        $this->preparar();
        // precisa de movimentação p/ fechar
        $this->actingAs($this->admin)->postJson('/api/admin/estoque/acerto', [
            'setor_id' => $this->setorA, 'produto_id' => $this->produtoId, 'movimentacao' => 'ENTRADA', 'quantidade' => 5, 'observacao' => 'c',
        ])->assertOk();

        $this->actingAs($this->admin)->postJson('/api/admin/estoque/fechamentos', [
            'datahorafechamento' => now()->toDateTimeString(),
        ])->assertCreated();

        $this->actingAs($this->admin)->getJson('/api/admin/estoque/fechamentos')->assertOk()->assertJsonStructure(['data' => [['id', 'datahorafechamento', 'reaberto']]]);
    }
}
