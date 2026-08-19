<?php

namespace Tests\Migration;

use App\Etl\Migrators\EmpresaConfigMigrator;
use App\Etl\Migrators\SatelitesMigrator;
use App\Etl\Support\MigrationContext;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\EmpresaConfig;
use App\Models\Produto\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Configuração operacional da empresa e signatário do comodato.
 *
 * Duas perdas silenciosas encontradas na varredura dos campos que a tela edita
 * e o banco não guarda:
 *
 *  1. `legado.empresaconfigs` tem ~95 chaves preenchidas e o migrator trazia
 *     CINCO. Plano de contas e centro de custo padrão, regra de estoque
 *     negativo, percentuais contábeis, defaults de NFC-e, malote, convênio e
 *     gás do povo ficavam para trás — a empresa migrava "configurada" com o
 *     default do sistema novo, e a divergência só apareceria na operação.
 *
 *  2. `legado.comodatos` guarda quem assinou o contrato (784 de 975 com nome) e
 *     o vencimento; nenhum dos dois vinha. Contrato de comodato sem signatário
 *     não vale como documento — e é o papel que protege o patrimônio da revenda.
 */
class ConfigOperacionalEComodatoTest extends TestCase
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

    public function test_config_operacional_da_empresa_e_migrada(): void
    {
        $empresa = Empresa::factory()->create();
        $leg = DB::connection($this->legadoConn);

        $leg->statement('create table empresaconfigs (id integer, grupo_id integer, empresa_id integer, planoconta_id integer, centrocusto_id integer, permiteestoquenegativo text, percentualencargos real, maloteconta_id integer, valorfretegp real, impressaoqtdviaspedido integer, diastrabalhadosemana integer, created_at text)');
        $leg->table('empresaconfigs')->insert([
            'id' => 1, 'grupo_id' => $empresa->grupo_id, 'empresa_id' => $empresa->id,
            'planoconta_id' => 516, 'centrocusto_id' => 357,
            'permiteestoquenegativo' => '0', 'percentualencargos' => 12.5,
            'maloteconta_id' => 44, 'valorfretegp' => 15, 'impressaoqtdviaspedido' => 2,
            'diastrabalhadosemana' => 6,
        ]);

        (new EmpresaConfigMigrator)->migrar($this->ctx());

        $dados = EmpresaConfig::query()->where('empresa_id', $empresa->id)->value('dados');

        $this->assertSame(516, $dados['planoconta_id']);
        $this->assertSame(357, $dados['centrocusto_id']);
        $this->assertSame(44, $dados['maloteconta_id'], 'o malote não fecharia sem a conta configurada');
        $this->assertSame(15, $dados['valorfretegp']);
        $this->assertSame(12.5, $dados['percentual_encargos']);
        $this->assertSame(6, $dados['dias_trabalhados_semana']);
    }

    /**
     * O legado guarda flag como texto '0'/'1'. Sem converter para boolean, a
     * tela recebe a STRING "0" — verdadeira em JavaScript — e o switch aparece
     * ligado com a configuração desligada.
     */
    public function test_flag_do_legado_vira_boolean_e_nao_string(): void
    {
        $empresa = Empresa::factory()->create();
        $leg = DB::connection($this->legadoConn);

        $leg->statement('create table empresaconfigs (id integer, grupo_id integer, empresa_id integer, permiteestoquenegativo text, validaatraso text, impressaoqtdviaspedido integer, created_at text)');
        $leg->table('empresaconfigs')->insert([
            'id' => 1, 'grupo_id' => $empresa->grupo_id, 'empresa_id' => $empresa->id,
            'permiteestoquenegativo' => '0', 'validaatraso' => '1',
            'impressaoqtdviaspedido' => 2,
        ]);

        (new EmpresaConfigMigrator)->migrar($this->ctx());

        $dados = EmpresaConfig::query()->where('empresa_id', $empresa->id)->value('dados');

        $this->assertFalse($dados['permite_estoque_negativo'], 'string "0" ligaria o switch');
        $this->assertTrue($dados['valida_atraso']);
        // Contra-prova: número de vias é contagem, não flag — tem de continuar int.
        $this->assertSame(2, $dados['impressao_vias_pedido']);
    }

    public function test_comodato_preserva_representante_e_vencimento(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        $leg = DB::connection($this->legadoConn);
        $leg->statement('create table comodatos (id integer, grupo_id integer, empresa_id integer, cliente_id integer, datacontrato text, datavencimento text, nomerepresentante text, cpfrepresentante text, rgrepresentante text, ativo text, created_at text)');
        $leg->table('comodatos')->insert([
            'id' => 1, 'grupo_id' => $empresa->grupo_id, 'empresa_id' => $empresa->id,
            'cliente_id' => $cliente->id,
            'datacontrato' => '2025-03-10', 'datavencimento' => '2026-03-10',
            'nomerepresentante' => 'Maria Souza', 'cpfrepresentante' => '123.456.789-00',
            'rgrepresentante' => '12.345.678-9', 'ativo' => '1',
        ]);
        $leg->statement('create table comodatoitems (id integer, comodato_id integer, produto_id integer, quantidade real)');
        $leg->table('comodatoitems')->insert([
            'id' => 1, 'comodato_id' => 1, 'produto_id' => $produto->id, 'quantidade' => 2,
        ]);

        (new SatelitesMigrator)->migrar($this->ctx());

        $c = DB::table('comodatos')->where('id', 1)->first();

        $this->assertNotNull($c, 'o comodato não migrou');
        $this->assertSame('Maria Souza', $c->nome_representante, 'o contrato sairia sem signatário');
        $this->assertSame('12345678900', $c->cpf_representante, 'CPF deve vir só com dígitos');
        $this->assertSame('2026-03-10', substr((string) $c->data_vencimento, 0, 10));
        // `datavencimento` é quando DEVERIA voltar; jogar em `data_devolucao`
        // (quando VOLTOU) dava um comodato aberto como se já tivesse retornado.
        $this->assertNull($c->data_devolucao);
    }
}
