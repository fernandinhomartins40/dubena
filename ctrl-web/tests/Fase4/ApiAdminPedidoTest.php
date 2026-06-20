<?php

namespace Tests\Fase4;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;

/**
 * F9 — API admin de PEDIDOS. Criação delega ao motor legado (orquestração atômica
 * estoque+financeiro, travada por PedidoBaselineTest). Lista/Kanban/ficha (read).
 */
class ApiAdminPedidoTest extends TestCase
{
    use DatabaseTransactions;

    private $admin;
    private $grupo;
    private $empresaId;

    private function cenario(): array
    {
        \Artisan::call('db:seed', ['--class' => '\DeployAdminSeeder', '--force' => true]);
        \Artisan::call('db:seed', ['--class' => '\RbacSeeder', '--force' => true]);
        $this->admin = \App\User::where('email', env('ADMIN_SEED_EMAIL', 'admin'))->first();
        $this->grupo = optional($this->admin->empresa)->grupo_id ?? 1;
        $this->empresaId = $this->admin->empresa_id;
        Session::put('empresa_padrao', \App\Empresa::find($this->empresaId));

        $setor = \DB::table('setors')->insertGetId(['grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'cidade_id' => 1, 'bairro_id' => 1, 'descricao' => 'Setor', 'numero' => '1', 'cep' => '00000000', 'latitude' => 0, 'longitude' => 0, 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $nfop = \DB::table('nfoperacaos')->insertGetId(['grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'descricao' => 'Venda', 'descricaofiscal' => 'Venda', 'movimentaestoque' => 1, 'movimentafinanceiro' => 1, 'aparecetela' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $plano = \DB::table('planocontas')->insertGetId(['grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'descricao' => 'Receita', 'pagarreceber' => 'R', 'codigo' => '1', 'nivel' => 1, 'insumo_valor' => 0, 'provisao' => 0, 'investimento' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $centro = \DB::table('centrocustos')->insertGetId(['grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'descricao' => 'Vendas', 'codigo' => '1', 'nivel' => 1, 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
        \App\Empresaconfig::updateOrCreate(['empresa_id' => $this->empresaId, 'grupo_id' => $this->grupo], ['permiteestoquenegativo' => 1, 'diastrabalhadosemana' => 6, 'setorprincipal_id' => $setor, 'maximoparcelas' => 1, 'pedidooperacao_id' => $nfop, 'planoconta_id' => $plano, 'centrocusto_id' => $centro]);

        $rua = \DB::table('ruas')->insertGetId(['grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'cidade_id' => 1, 'bairro_id' => 1, 'descricao' => 'Rua T', 'importacaocep_id' => -1, 'nfecompl' => 'Rua', 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $classe = \DB::table('produtoclasses')->insertGetId(['grupo_id' => $this->grupo, 'descricao' => 'C', 'tipo' => 'P', 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $unidade = \DB::table('unidademedidas')->insertGetId(['grupo_id' => $this->grupo, 'descricao' => 'UN', 'sigla' => 'UN', 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $produto = \DB::table('produtos')->insertGetId([
            'grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'produtoclasse_id' => $classe, 'unidademedida_id' => $unidade,
            'descricao' => 'Botijão', 'vasilhameretornavel' => false, 'ativo' => 1, 'nfepermite' => 0,
            'customedio' => 50, 'custofrete' => 0, 'precovenda' => 100, 'precovendaminimo' => 0, 'pesoliquido' => 0, 'pesobruto' => 0,
            'observacao' => '', 'ean' => '', 'ncm' => '', 'especie' => '', 'marca' => '', 'nfedescricaofiscal' => '',
            'nfetipoitem' => 0, 'nfeextipi' => '', 'nfecodgen' => 0, 'nfecodlst' => 0, 'nfenatrec' => '',
            'nfecodenquadramentoipi' => 0, 'nfecprodanp' => '', 'nfeqbcprod' => 0, 'nfevaliqprod' => 0, 'nfevcide' => 0,
            'pGNi' => 0, 'pGNn' => 0, 'pGLP' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        \DB::table('estoquesetors')->insert(['grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'setor_id' => $setor, 'produto_id' => $produto, 'quantidade' => 100, 'quantidademinima' => 0, 'quantidademaxima' => 1000, 'created_at' => now(), 'updated_at' => now()]);
        $cond = \DB::table('condicaopagamentos')->insertGetId(['grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'descricao' => 'À vista', 'tipo' => 1, 'num_parcelas' => 1, 'dias_primeira' => 0, 'intervalo' => 0, 'ativo' => 1, 'taxa' => 0, 'created_at' => now(), 'updated_at' => now()]);
        \DB::table('condicaopagamentoparcelas')->insert(['condicaopagamento_id' => $cond, 'dias' => 0, 'percentualvalor' => 100, 'created_at' => now(), 'updated_at' => now()]);
        $sit = \DB::table('pedidosituacaos')->insertGetId(['grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'descricao' => 'Concluído', 'ativo' => 1, 'fechadoconcluido' => 1, 'entregafinalizada' => 1, 'entregapendente' => 0, 'entregacancelada' => 0, 'fechadocancelado' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $cli = \DB::table('clientes')->insertGetId(['grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'nome' => 'Cli Ped', 'numero' => '100', 'cidade_id' => 1, 'bairro_id' => 1, 'rua_id' => $rua, 'uf' => 'PR', 'observacoes' => '', 'conveniolimite' => 0, 'latitude' => -25.4, 'longitude' => -49.2, 'locationtype' => 'APPROXIMATE', 'cliente' => 1, 'fornecedor' => 0, 'transportador' => 0, 'nfemite' => 0, 'convenio' => 0, 'consumidor_final' => 0, 'simples' => 0, 'gasdopovo' => 0, 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $colab = \DB::table('colaboradors')->insertGetId(['grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'nome' => 'Entreg', 'numero' => '1', 'cidade_id' => 1, 'bairro_id' => 1, 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
        // pedidos.pedidooperacao_id é FK p/ pedidooperacaos (não nfoperacaos)
        $pedoper = \DB::table('pedidooperacaos')->insertGetId(['grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'descricao' => 'Venda', 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return compact('setor', 'nfop', 'pedoper', 'rua', 'produto', 'cond', 'sit', 'cli', 'colab');
    }

    public function test_exige_autenticacao()
    {
        $this->getJson('/api/admin/pedidos')->assertUnauthorized();
    }

    public function test_criar_pedido_via_api_movimenta_estoque()
    {
        $c = $this->cenario();
        $resp = $this->actingAs($this->admin)->postJson('/api/admin/pedidos', [
            'cliente_id' => $c['cli'], 'condicaopagamento_id' => $c['cond'], 'pedidooperacao_id' => $c['pedoper'],
            'pedidosituacao_id' => $c['sit'], 'entregasetor_id' => $c['setor'], 'colaborador_id' => $c['colab'],
            'entregarua_id' => $c['rua'], 'entregabairro_id' => 1, 'entregacidade_id' => 1, 'entreganumero' => '100',
            'ufentrega' => 'PR', 'valorvenda' => 200, 'latitude' => -25.4, 'longitude' => -49.2,
            'itens' => [['produto_id' => $c['produto'], 'quantidade' => 2, 'preco_unitario' => 100]],
        ]);
        $resp->assertCreated();
        $pedidoId = $resp->json('data.id');
        $this->assertNotNull($pedidoId);
        // orquestração: estoque baixou 100 → 98
        $this->assertEquals(98, (float) \DB::table('estoquesetors')->where(['setor_id' => $c['setor'], 'produto_id' => $c['produto']])->value('quantidade'));

        // ficha
        $this->actingAs($this->admin)->getJson("/api/admin/pedidos/$pedidoId")->assertOk()->assertJsonPath('data.cliente', 'Cli Ped')->assertJsonStructure(['data' => ['itens']]);
    }

    public function test_lista_e_kanban()
    {
        $this->cenario();
        $this->actingAs($this->admin)->getJson('/api/admin/pedidos')->assertOk()->assertJsonStructure(['data', 'meta']);
        $this->actingAs($this->admin)->getJson('/api/admin/pedidos/kanban')->assertOk()->assertJsonStructure(['data' => [['situacao_id', 'descricao', 'total', 'pedidos']]]);
        $this->actingAs($this->admin)->getJson('/api/admin/pedidos/situacoes')->assertOk()->assertJsonStructure(['data' => [['id', 'descricao']]]);
    }

    public function test_criar_valida_obrigatorios()
    {
        $this->cenario();
        $this->actingAs($this->admin)->postJson('/api/admin/pedidos', [])
            ->assertStatus(422)->assertJsonValidationErrors(['cliente_id', 'pedidosituacao_id', 'itens']);
    }
}
