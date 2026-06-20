<?php

namespace Tests\Fase4;

use Tests\TestCase;
use App\Http\Controllers\PedidoController;
use App\Http\Requests\PedidoRequest;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;

/**
 * F9 — BASELINE (caracterização) do PedidoController::store ANTES de expor na API.
 * O store é o coração transacional: cria pedido + orquestra ESTOQUE (movimentarEstoque
 * quando situação fechadoconcluido) + FINANCEIRO. Este teste TRAVA esse comportamento
 * (saldo de estoque cai; pedido+itens persistem) para detectar regressão. Não altera o motor.
 *
 * Evita a chamada externa ao Google Maps usando o MESMO endereço do cliente (o
 * PedidoUtil::dadosExtras só chama buscaLatitudeLongitude quando o endereço muda).
 */
class PedidoBaselineTest extends TestCase
{
    use DatabaseTransactions;

    private $admin;
    private $grupo;
    private $empresaId;

    private function montarCenario(): array
    {
        \Artisan::call('db:seed', ['--class' => '\DeployAdminSeeder', '--force' => true]);
        \Artisan::call('db:seed', ['--class' => '\RbacSeeder', '--force' => true]);
        $this->admin = \App\User::where('email', env('ADMIN_SEED_EMAIL', 'admin'))->first();
        \Auth::login($this->admin);
        $this->grupo = optional($this->admin->empresa)->grupo_id ?? 1;
        $this->empresaId = $this->admin->empresa_id;
        Session::put('empresa_padrao', \App\Empresa::find($this->empresaId));
        // PedidoPolicy lê Session('permissoes') (populada no login legado). Em teste/API
        // admin, populamos a permissão de pedido (criar/editar) para o motor autorizar.
        Session::put('permissoes', collect([(object) ['descricao' => 'pedido.index', 'criar' => 1, 'editar' => 1, 'deletar' => 1, 'visualizar' => 1]]));

        // Setor (NOT NULL: cidade/bairro=1 do seed)
        $setor = \DB::table('setors')->insertGetId([
            'grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'cidade_id' => 1, 'bairro_id' => 1,
            'descricao' => 'Setor Venda', 'numero' => '1', 'cep' => '00000000', 'latitude' => 0, 'longitude' => 0,
            'ativo' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Operação fiscal (nfoperacaos) que define movimenta estoque/financeiro do pedido.
        $nfop = \DB::table('nfoperacaos')->insertGetId([
            'grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'descricao' => 'Venda', 'descricaofiscal' => 'Venda',
            'movimentaestoque' => 1, 'movimentafinanceiro' => 1, 'aparecetela' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        // Plano/Centro de contas (rateio do financeiro exige no config)
        $plano = \DB::table('planocontas')->insertGetId(['grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'descricao' => 'Receita', 'pagarreceber' => 'R', 'codigo' => '1', 'nivel' => 1, 'insumo_valor' => 0, 'provisao' => 0, 'investimento' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $centro = \DB::table('centrocustos')->insertGetId(['grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'descricao' => 'Vendas', 'codigo' => '1', 'nivel' => 1, 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
        // Config da empresa — pedidooperacao_id → nfoperacao + plano/centro p/ rateio
        \App\Empresaconfig::updateOrCreate(
            ['empresa_id' => $this->empresaId, 'grupo_id' => $this->grupo],
            ['permiteestoquenegativo' => 1, 'diastrabalhadosemana' => 6, 'setorprincipal_id' => $setor, 'maximoparcelas' => 1, 'pedidooperacao_id' => $nfop, 'planoconta_id' => $plano, 'centrocusto_id' => $centro]
        );

        // Rua/bairro do cliente (endereço idêntico evita chamada ao Maps)
        $rua = \DB::table('ruas')->insertGetId([
            'grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'cidade_id' => 1, 'bairro_id' => 1,
            'descricao' => 'Rua Teste', 'importacaocep_id' => -1, 'nfecompl' => 'Rua', 'ativo' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Produto + unidade/classe
        $classe = \DB::table('produtoclasses')->insertGetId(['grupo_id' => $this->grupo, 'descricao' => 'C', 'tipo' => 'P', 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $unidade = \DB::table('unidademedidas')->insertGetId(['grupo_id' => $this->grupo, 'descricao' => 'UN', 'sigla' => 'UN', 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $produto = \DB::table('produtos')->insertGetId([
            'grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'produtoclasse_id' => $classe, 'unidademedida_id' => $unidade,
            'descricao' => 'Botijão P13', 'vasilhameretornavel' => false, 'ativo' => 1, 'nfepermite' => 0,
            'customedio' => 50, 'custofrete' => 0, 'precovenda' => 100, 'precovendaminimo' => 0, 'pesoliquido' => 0, 'pesobruto' => 0,
            'observacao' => '', 'ean' => '', 'ncm' => '', 'especie' => '', 'marca' => '', 'nfedescricaofiscal' => '',
            'nfetipoitem' => 0, 'nfeextipi' => '', 'nfecodgen' => 0, 'nfecodlst' => 0, 'nfenatrec' => '',
            'nfecodenquadramentoipi' => 0, 'nfecprodanp' => '', 'nfeqbcprod' => 0, 'nfevaliqprod' => 0, 'nfevcide' => 0,
            'pGNi' => 0, 'pGNn' => 0, 'pGLP' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        // saldo inicial de estoque
        \DB::table('estoquesetors')->insert([
            'grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'setor_id' => $setor, 'produto_id' => $produto,
            'quantidade' => 100, 'quantidademinima' => 0, 'quantidademaxima' => 1000, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Condição de pagamento (à vista) + operação + situação CONCLUÍDA
        $cond = \DB::table('condicaopagamentos')->insertGetId(['grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'descricao' => 'À vista', 'tipo' => 1, 'num_parcelas' => 1, 'dias_primeira' => 0, 'intervalo' => 0, 'ativo' => 1, 'taxa' => 0, 'created_at' => now(), 'updated_at' => now()]);
        \DB::table('condicaopagamentoparcelas')->insert(['condicaopagamento_id' => $cond, 'dias' => 0, 'percentualvalor' => 100, 'created_at' => now(), 'updated_at' => now()]);
        $oper = \DB::table('pedidooperacaos')->insertGetId(['grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'descricao' => 'Venda', 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
        // situação concluída — dispara estoque (SAÍDA) + financeiro
        $sit = \DB::table('pedidosituacaos')->insertGetId(['grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'descricao' => 'Concluído', 'ativo' => 1, 'fechadoconcluido' => 1, 'entregafinalizada' => 1, 'entregapendente' => 0, 'entregacancelada' => 0, 'fechadocancelado' => 0, 'created_at' => now(), 'updated_at' => now()]);

        // Cliente (com rua/numero/lat/long definidos = endereço de entrega idêntico)
        $cli = \DB::table('clientes')->insertGetId([
            'grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'nome' => 'Cli Pedido', 'numero' => '100',
            'cidade_id' => 1, 'bairro_id' => 1, 'rua_id' => $rua, 'uf' => 'PR', 'observacoes' => '',
            'conveniolimite' => 0, 'latitude' => -25.4, 'longitude' => -49.2, 'locationtype' => 'APPROXIMATE',
            'cliente' => 1, 'fornecedor' => 0, 'transportador' => 0, 'nfemite' => 0, 'convenio' => 0,
            'consumidor_final' => 0, 'simples' => 0, 'gasdopovo' => 0, 'ativo' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Colaborador (pedidos.colaborador_id é FK p/ colaboradors, não users)
        $colab = \DB::table('colaboradors')->insertGetId([
            'grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'nome' => 'Entregador', 'numero' => '1',
            'cidade_id' => 1, 'bairro_id' => 1, 'ativo' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return compact('setor', 'rua', 'produto', 'cond', 'oper', 'sit', 'cli', 'colab');
    }

    public function test_store_cria_pedido_concluido_e_movimenta_estoque()
    {
        $c = $this->montarCenario();

        // produtospedido: [ [P{id}|descricao, descricao, precoUnit, qtde, ...], ... ] (formato do form legado)
        $produtospedido = json_encode([[ (string) $c['produto'], 'Botijão P13', '100,00', 2 ]]);

        $payload = [
            'empresa_id'        => $this->empresaId,
            'cliente_id'        => $c['cli'],
            'condicaopagamento_id' => $c['cond'],
            'pedidooperacao_id' => $c['oper'],
            'pedidosituacao_id' => $c['sit'],
            'entregasetor_id'   => $c['setor'],
            'colaborador_id'    => $c['colab'],
            'entregarua_id'     => $c['rua'],
            'entregabairro_id'  => 1,
            'entregacidade_id'  => 1,
            'entreganumero'     => '100',
            'ufentrega'         => 'PR',
            'entregalatitude'   => -25.4,
            'entregalongitude'  => -49.2,
            'entregatelefone'   => '',
            'entregataxa'       => '0,00',
            'valorvenda'        => '200,00',
            'valordesconto'     => '0,00',
            'datahoraacao'      => date('d/m/Y H:i:s'),
            'datahoraprevisaoentrega' => date('d/m/Y H:i:s'),
            'numerocartao'      => '',
            'produtospedido'    => $produtospedido,
        ];

        $request = PedidoRequest::create('/api/pedido', 'POST', $payload);
        $request->setUserResolver(fn () => $this->admin);

        $controller = app(PedidoController::class);
        $resp = $controller->store($request, new \App\Pedido(), true);

        // pedido criado
        $pedido = \App\Pedido::where('cliente_id', $c['cli'])->latest('id')->first();
        $this->assertNotNull($pedido, 'Pedido não foi criado: ' . json_encode($resp));
        $this->assertEquals(1, \DB::table('pedidoitems')->where('pedido_id', $pedido->id)->count());

        // estoque baixou (100 - 2 = 98) — orquestração atômica do motor
        $saldo = \DB::table('estoquesetors')->where(['setor_id' => $c['setor'], 'produto_id' => $c['produto']])->value('quantidade');
        $this->assertEquals(98, (float) $saldo);
    }
}
