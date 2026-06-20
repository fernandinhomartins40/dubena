<?php

namespace Tests\Fase4;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;

/**
 * F6 — API admin FINANCEIRO: Lançamentos (listar/resumo/criar via Processor) +
 * Plano/Centro de contas (CRUD).
 */
class ApiAdminFinanceiroTest extends TestCase
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
        Session::put('empresa_padrao', \App\Empresa::find($this->empresaId));
    }

    private function cliente(): int
    {
        return \DB::table('clientes')->insertGetId([
            'grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'nome' => 'Cli Fin ' . uniqid(), 'numero' => '1',
            'cidade_id' => 1, 'observacoes' => '', 'conveniolimite' => 0, 'latitude' => 0, 'longitude' => 0,
            'locationtype' => 'APPROXIMATE', 'cliente' => 1, 'fornecedor' => 0, 'transportador' => 0,
            'nfemite' => 0, 'convenio' => 0, 'consumidor_final' => 0, 'simples' => 0, 'ativo' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_exige_autenticacao()
    {
        $this->getJson('/api/admin/financeiro/lancamentos')->assertUnauthorized();
    }

    public function test_plano_conta_crud()
    {
        $this->preparar();
        $id = $this->actingAs($this->admin)->postJson('/api/admin/financeiro/planos-conta', ['descricao' => 'Receitas', 'codigo' => '1', 'pagarreceber' => 'R'])
            ->assertCreated()->json('data.id');
        $this->actingAs($this->admin)->getJson('/api/admin/financeiro/planos-conta')->assertOk()->assertJsonStructure(['data' => [['id', 'descricao', 'codigo']]]);
        $this->actingAs($this->admin)->putJson("/api/admin/financeiro/planos-conta/$id", ['descricao' => 'Receitas Op.'])->assertOk()->assertJsonPath('data.descricao', 'Receitas Op.');
        $this->actingAs($this->admin)->deleteJson("/api/admin/financeiro/planos-conta/$id")->assertOk();
    }

    public function test_centro_custo_crud()
    {
        $this->preparar();
        $id = $this->actingAs($this->admin)->postJson('/api/admin/financeiro/centros-custo', ['descricao' => 'Administrativo', 'codigo' => '1'])
            ->assertCreated()->json('data.id');
        $this->actingAs($this->admin)->getJson('/api/admin/financeiro/centros-custo')->assertOk()->assertJsonStructure(['data' => [['id', 'descricao']]]);
        $this->actingAs($this->admin)->deleteJson("/api/admin/financeiro/centros-custo/$id")->assertOk();
    }

    public function test_criar_lancamento_e_listar()
    {
        $this->preparar();
        $cli = $this->cliente();
        $pc = $this->actingAs($this->admin)->postJson('/api/admin/financeiro/planos-conta', ['descricao' => 'PC', 'codigo' => '1', 'pagarreceber' => 'R'])->json('data.id');
        $cc = $this->actingAs($this->admin)->postJson('/api/admin/financeiro/centros-custo', ['descricao' => 'CC', 'codigo' => '1'])->json('data.id');
        $cond = \DB::table('condicaopagamentos')->insertGetId(['grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'descricao' => 'À vista', 'tipo' => 1, 'num_parcelas' => 1, 'dias_primeira' => 0, 'intervalo' => 0, 'ativo' => 1, 'taxa' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $resp = $this->actingAs($this->admin)->postJson('/api/admin/financeiro/lancamentos', [
            'cliente_id' => $cli, 'pagarreceber' => 'R', 'descricao' => 'Venda X', 'documento' => 'NF1',
            'valor' => 200, 'dataemissao' => '2026-06-01', 'datacompetencia' => '2026-06-01', 'datavencimento' => '2026-06-15',
            'planoconta_id' => $pc, 'centrocusto_id' => $cc, 'condicaopagamento_id' => $cond,
        ]);
        $resp->assertCreated();
        $finId = $resp->json('data.id');
        $this->assertEquals(200, (float) \DB::table('financeiroparcelas')->where('financeiro_id', $finId)->value('valor'));

        // lista filtrada por R e aberto
        $lista = $this->actingAs($this->admin)->getJson('/api/admin/financeiro/lancamentos?pagarreceber=R&status=aberto&q=Venda X');
        $lista->assertOk()->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
        $this->assertGreaterThanOrEqual(1, count($lista->json('data')));

        // resumo
        $this->actingAs($this->admin)->getJson('/api/admin/financeiro/lancamentos/resumo')
            ->assertOk()->assertJsonStructure(['data' => ['receber_aberto', 'receber_baixado', 'pagar_aberto', 'pagar_baixado']]);
    }

    public function test_lancamento_valida_obrigatorios()
    {
        $this->preparar();
        $this->actingAs($this->admin)->postJson('/api/admin/financeiro/lancamentos', [])
            ->assertStatus(422)->assertJsonValidationErrors(['cliente_id', 'pagarreceber', 'valor', 'planoconta_id']);
    }

    private function contatipo(): int
    {
        return \DB::table('contatipos')->insertGetId([
            'grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'descricao' => 'Caixa', 'perfil' => 1,
            'ativo' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Cria uma conta-caixa fechada + vínculo do admin (operar/transferir). */
    private function conta(): int
    {
        $contaId = \DB::table('contas')->insertGetId([
            'grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'contatipo_id' => $this->contatipo(),
            'descricao' => 'Caixa Geral',
            'conta' => '1', 'saldoinicial' => 0, 'saldoatual' => 0, 'fechado' => 1, 'ativo' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        \DB::table('contausers')->insert([
            'conta_id' => $contaId, 'user_id' => $this->admin->id, 'operar' => 1, 'visualizar' => 1,
            'transferir' => 1, 'estornar' => 1, 'lancarfechado' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return $contaId;
    }

    public function test_caixa_contas_lista()
    {
        $this->preparar();
        $this->conta();
        $this->actingAs($this->admin)->getJson('/api/admin/caixa/contas')
            ->assertOk()->assertJsonStructure(['data' => [['id', 'descricao', 'saldoatual', 'fechado']]]);
    }

    public function test_caixa_abrir_e_fechar()
    {
        $this->preparar();
        $contaId = $this->conta();

        // abre
        $this->actingAs($this->admin)->postJson("/api/admin/caixa/$contaId/abrir", [
            'datahoraabertura' => now()->toDateTimeString(),
        ])->assertCreated();
        $this->assertEquals(0, (int) \App\Conta::find($contaId)->fechado);

        // movimentos + saldo
        $this->actingAs($this->admin)->getJson("/api/admin/caixa/$contaId/movimentos")
            ->assertOk()->assertJsonStructure(['data', 'saldo', 'fechado']);

        // fecha (sem movimentos posteriores → ok)
        $this->actingAs($this->admin)->postJson("/api/admin/caixa/$contaId/fechar", [
            'datahorafechamento' => now()->addMinute()->toDateTimeString(),
        ])->assertOk();
        $this->assertEquals(1, (int) \App\Conta::find($contaId)->fechado);
    }

    public function test_caixa_abrir_exige_permissao()
    {
        $this->preparar();
        // conta SEM vínculo contausers → não pode abrir
        $contaId = \DB::table('contas')->insertGetId([
            'grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'contatipo_id' => $this->contatipo(),
            'descricao' => 'Caixa Sem Vínculo',
            'conta' => '2', 'saldoinicial' => 0, 'saldoatual' => 0, 'fechado' => 1, 'ativo' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($this->admin)->postJson("/api/admin/caixa/$contaId/abrir", [
            'datahoraabertura' => now()->toDateTimeString(),
        ])->assertStatus(422);
    }
}
