<?php

namespace Tests\Migration;

use App\Etl\Migrators\CaixaMigrator;
use App\Etl\Migrators\PedidosMigrator;
use App\Etl\Support\MigrationContext;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Produto\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Vínculos que o ETL lia da origem mas não gravava no destino.
 *
 * O modo de falha é silencioso e sobrevive a todas as invariantes existentes: a
 * `CountInvariant` compara a QUANTIDADE de linhas (400.070 = 400.070, passa) e
 * nada olha se as COLUNAS da linha chegaram preenchidas. Uma FK esquecida no
 * `mapearPedido()` não aparece em lugar nenhum do relatório do `etl:run`.
 *
 * Em produção isto custou:
 *  - 400.070 pedidos sem `condicaopagamento_id` — o `FinanceiroService` decide o
 *    lançamento por ele (à vista × a prazo) e o `MaloteService` confere o caixa
 *    pelo mesmo campo; o histórico perdeu a forma de pagamento;
 *  - 7 contas marcadas como BANCO sem dizer QUAL banco (o `banco_id` era lido
 *    para derivar o `tipo` e descartado em seguida).
 */
class FksNaoMapeadasTest extends TestCase
{
    use RefreshDatabase;

    private string $legadoConn = 'legado_teste';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set("database.connections.{$this->legadoConn}", [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::connection($this->legadoConn)->getPdo();
    }

    private function ctx(): MigrationContext
    {
        return new MigrationContext(conexaoLegado: $this->legadoConn);
    }

    public function test_pedido_preserva_a_condicao_de_pagamento(): void
    {
        $empresa = Empresa::factory()->create();
        $leg = DB::connection($this->legadoConn);

        $leg->statement('create table condicaopagamentos (id integer, grupo_id integer, descricao text, num_parcelas integer, intervalo integer, dias_primeira integer, ativo text, created_at text)');
        $leg->table('condicaopagamentos')->insert([
            ['id' => 25, 'grupo_id' => $empresa->grupo_id, 'descricao' => 'À vista', 'num_parcelas' => 1, 'intervalo' => 0, 'dias_primeira' => 0, 'ativo' => '1'],
            ['id' => 45, 'grupo_id' => $empresa->grupo_id, 'descricao' => '30 dias', 'num_parcelas' => 1, 'intervalo' => 30, 'dias_primeira' => 30, 'ativo' => '1'],
        ]);

        $leg->statement('create table pedidooperacaos (id integer, grupo_id integer, descricao text, ativo integer)');
        $leg->statement('create table pedidosituacaos (id integer, grupo_id integer, descricao text, fechadoconcluido integer, fechadocancelado integer, entregafinalizada integer, entregacancelada integer, ativo integer)');
        $leg->table('pedidosituacaos')->insert([
            'id' => 7, 'grupo_id' => $empresa->grupo_id, 'descricao' => 'Entregue',
            'fechadoconcluido' => 1, 'fechadocancelado' => 0, 'entregafinalizada' => 1, 'entregacancelada' => 0, 'ativo' => 1,
        ]);

        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        $leg->statement('create table pedidos (id integer, empresa_id integer, grupo_id integer, cliente_id integer, pedidooperacao_id integer, pedidosituacao_id integer, condicaopagamento_id integer, entregasetor_id integer, atendenteuser_id integer, entregadoruser_id integer, datahora text, datahoraacao text, entregaurgente integer, entregataxa real, entregatrocopara real, valorvenda real, valordesconto real, observacao text, fechadoconcluido integer)');
        $leg->table('pedidos')->insert([
            'id' => 1001, 'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => 7,
            'condicaopagamento_id' => 45, 'valorvenda' => 120.0, 'valordesconto' => 0,
            'entregaurgente' => 0, 'entregataxa' => 0, 'fechadoconcluido' => 1,
        ]);

        (new PedidosMigrator)->migrar($this->ctx());

        $this->assertSame(
            45,
            (int) DB::table('pedidos')->where('id', 1001)->value('condicaopagamento_id'),
            'o pedido migrou sem a forma de pagamento — o financeiro não sabe se foi à vista ou a prazo',
        );
    }

    /**
     * A condição duplicada por descrição precisa cair na canônica.
     *
     * O destino tem UNIQUE(grupo_id, descricao) e o legado repete descrições. O
     * `ComplementosMigrator` já resolvia isso para as parcelas; se os dois
     * migrators divergirem no critério, o segundo a rodar aponta para outro id.
     */
    public function test_condicao_duplicada_cai_na_canonica(): void
    {
        $empresa = Empresa::factory()->create();
        $leg = DB::connection($this->legadoConn);

        $leg->statement('create table condicaopagamentos (id integer, grupo_id integer, descricao text, num_parcelas integer, intervalo integer, dias_primeira integer, ativo text, created_at text)');
        $leg->table('condicaopagamentos')->insert([
            ['id' => 10, 'grupo_id' => $empresa->grupo_id, 'descricao' => 'NÃO USAR', 'num_parcelas' => 1, 'intervalo' => 0, 'dias_primeira' => 0, 'ativo' => '1'],
            ['id' => 99, 'grupo_id' => $empresa->grupo_id, 'descricao' => 'NÃO USAR', 'num_parcelas' => 1, 'intervalo' => 0, 'dias_primeira' => 0, 'ativo' => '1'],
        ]);

        $leg->statement('create table pedidooperacaos (id integer, grupo_id integer, descricao text, ativo integer)');
        $leg->statement('create table pedidosituacaos (id integer, grupo_id integer, descricao text, fechadoconcluido integer, fechadocancelado integer, entregafinalizada integer, entregacancelada integer, ativo integer)');
        $leg->table('pedidosituacaos')->insert([
            'id' => 7, 'grupo_id' => $empresa->grupo_id, 'descricao' => 'Entregue',
            'fechadoconcluido' => 1, 'fechadocancelado' => 0, 'entregafinalizada' => 1, 'entregacancelada' => 0, 'ativo' => 1,
        ]);

        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        $leg->statement('create table pedidos (id integer, empresa_id integer, grupo_id integer, cliente_id integer, pedidooperacao_id integer, pedidosituacao_id integer, condicaopagamento_id integer, entregasetor_id integer, atendenteuser_id integer, entregadoruser_id integer, datahora text, datahoraacao text, entregaurgente integer, entregataxa real, entregatrocopara real, valorvenda real, valordesconto real, observacao text, fechadoconcluido integer)');
        $leg->table('pedidos')->insert([
            'id' => 2002, 'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => 7,
            'condicaopagamento_id' => 99, // a duplicada
            'valorvenda' => 50.0, 'valordesconto' => 0,
            'entregaurgente' => 0, 'entregataxa' => 0, 'fechadoconcluido' => 1,
        ]);

        (new PedidosMigrator)->migrar($this->ctx());

        $this->assertSame(
            10,
            (int) DB::table('pedidos')->where('id', 2002)->value('condicaopagamento_id'),
            'a condição duplicada deveria ter sido remapeada para a canônica (id 10)',
        );
        $this->assertFalse(
            DB::table('condicaopagamentos')->where('id', 99)->exists(),
            'a duplicada não pode ser gravada: o destino tem UNIQUE(grupo_id, descricao)',
        );
    }

    /**
     * O item do pedido tem de chegar com o PREÇO que foi cobrado.
     *
     * O migrator lia `precovenda`/`preco`; as colunas reais de
     * `legado.pedidoprodutos` são `precovendaunitario` e `precovendatotal`.
     * Como `?? 0` fechava a conta, **406.883 itens migraram com R$ 0,00** — o
     * pedido exibia o total certo e cada item como "— × 1  R$ 0,00".
     *
     * Nenhuma invariante pegava: a CountInvariant confere a QUANTIDADE de itens,
     * que estava certa.
     */
    public function test_item_do_pedido_preserva_o_preco_cobrado(): void
    {
        $empresa = Empresa::factory()->create();
        $leg = DB::connection($this->legadoConn);

        $leg->statement('create table pedidooperacaos (id integer, grupo_id integer, descricao text, ativo integer)');
        $leg->statement('create table pedidosituacaos (id integer, grupo_id integer, descricao text, fechadoconcluido integer, fechadocancelado integer, entregafinalizada integer, entregacancelada integer, ativo integer)');
        $leg->table('pedidosituacaos')->insert([
            'id' => 7, 'grupo_id' => $empresa->grupo_id, 'descricao' => 'Entregue',
            'fechadoconcluido' => 1, 'fechadocancelado' => 0, 'entregafinalizada' => 1, 'entregacancelada' => 0, 'ativo' => 1,
        ]);

        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'descricao' => 'Glp P13',
        ]);

        $leg->statement('create table pedidos (id integer, empresa_id integer, grupo_id integer, cliente_id integer, pedidooperacao_id integer, pedidosituacao_id integer, condicaopagamento_id integer, entregasetor_id integer, atendenteuser_id integer, entregadoruser_id integer, datahora text, datahoraacao text, entregaurgente integer, entregataxa real, entregatrocopara real, valorvenda real, valordesconto real, observacao text, fechadoconcluido integer)');
        $leg->table('pedidos')->insert([
            'id' => 456548, 'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => 7,
            'valorvenda' => 110.0, 'valordesconto' => 0,
            'entregaurgente' => 0, 'entregataxa' => 0, 'fechadoconcluido' => 1,
        ]);

        // Nomes REAIS do legado — o teste antigo teria passado com nomes inventados.
        $leg->statement('create table pedidoprodutos (id integer, pedido_id integer, produto_id integer, quantidade real, precovendaunitario real, precovendatotal real, customedio real, created_at text)');
        $leg->table('pedidoprodutos')->insert([
            'id' => 697072, 'pedido_id' => 456548, 'produto_id' => $produto->id,
            'quantidade' => 1, 'precovendaunitario' => 110, 'precovendatotal' => 110,
            'customedio' => 75.2648,
        ]);

        (new PedidosMigrator)->migrar($this->ctx());

        $item = DB::table('pedidoitens')->where('id', 697072)->first();

        $this->assertNotNull($item, 'o item do pedido não migrou');
        $this->assertSame(110.0, (float) $item->preco_unitario, 'o item migrou com preço R$ 0,00');
        $this->assertSame(110.0, (float) $item->valor_total);
    }

    public function test_conta_preserva_o_banco(): void
    {
        $empresa = Empresa::factory()->create();
        $leg = DB::connection($this->legadoConn);

        DB::table('bancos')->insert([
            'id' => 341, 'grupo_id' => $empresa->grupo_id, 'descricao' => 'Itaú', 'ativo' => true,
        ]);

        $leg->statement('create table contas (id integer, empresa_id integer, grupo_id integer, descricao text, banco_id integer, agencia text, conta text, saldoinicial real, saldoatual real, fechado integer, created_at text)');
        $leg->table('contas')->insert([
            'id' => 5, 'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Conta corrente', 'banco_id' => 341, 'agencia' => '1234',
            'conta' => '56789-0', 'saldoinicial' => 0, 'saldoatual' => 0, 'fechado' => 0,
        ]);

        (new CaixaMigrator)->migrar($this->ctx());

        $conta = DB::table('contas')->where('id', 5)->first();
        $this->assertSame('BANCO', $conta->tipo);
        $this->assertSame(341, (int) $conta->banco_id, 'a conta migrou como BANCO sem dizer qual banco');
    }

    /** Banco ausente no destino vira null, não derruba a carga. */
    public function test_conta_com_banco_inexistente_nao_quebra_a_carga(): void
    {
        $empresa = Empresa::factory()->create();
        $leg = DB::connection($this->legadoConn);

        $leg->statement('create table contas (id integer, empresa_id integer, grupo_id integer, descricao text, banco_id integer, agencia text, conta text, saldoinicial real, saldoatual real, fechado integer, created_at text)');
        $leg->table('contas')->insert([
            'id' => 6, 'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Conta órfã', 'banco_id' => 999, // banco que não existe no destino
            'saldoinicial' => 0, 'saldoatual' => 0, 'fechado' => 0,
        ]);

        (new CaixaMigrator)->migrar($this->ctx());

        $conta = DB::table('contas')->where('id', 6)->first();
        $this->assertNotNull($conta, 'a conta não podia ser descartada por causa de um banco ausente');
        $this->assertNull($conta->banco_id);
    }
}
