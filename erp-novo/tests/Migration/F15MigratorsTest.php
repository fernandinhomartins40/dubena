<?php

namespace Tests\Migration;

use App\Etl\MigratorRegistry;
use App\Etl\Migrators\CrmMigrator;
use App\Etl\Migrators\FrotaMigrator;
use App\Etl\Migrators\PagamentoMigrator;
use App\Etl\Migrators\RhMigrator;
use App\Etl\Support\MigrationContext;
use App\Models\Crm\PosVenda;
use App\Models\Crm\Promocao;
use App\Models\Empresa;
use App\Models\Frota\Veiculo;
use App\Models\Frota\VeiculoAbastecimento;
use App\Models\Pagamento\CartaoTransacao;
use App\Models\Rh\Colaborador;
use App\Models\Rh\ColaboradorFamilia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F15 — ETL da cauda longa (RH, frota, CRM, pagamentos). Simula o banco LEGADO
 * com uma conexão sqlite em memória populada com o SCHEMA REAL do legado
 * (auditoria 2026-08-14: os testes antigos simulavam um schema inventado — a
 * mesma causa que fez os migrators migrarem zero em produção sem ninguém ver).
 */
class F15MigratorsTest extends TestCase
{
    use RefreshDatabase;

    private string $legadoConn = 'legado_teste';

    protected function setUp(): void
    {
        parent::setUp();

        // Conexão "legado" simulada (sqlite em memória própria).
        config()->set("database.connections.{$this->legadoConn}", [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        // Força criação do PDO compartilhado para a conexão em memória persistir.
        DB::connection($this->legadoConn)->getPdo();
    }

    private function ctx(): MigrationContext
    {
        return new MigrationContext(conexaoLegado: $this->legadoConn);
    }

    private function empresa(): Empresa
    {
        return Empresa::factory()->create();
    }

    public function test_rh_migra_colaboradores_com_vinculo_user_e_familia(): void
    {
        $empresa = $this->empresa();
        $leg = DB::connection($this->legadoConn);

        // Schema REAL: sem colunas user_id/telefone/entregador no colaborador.
        $leg->statement('create table colaboradores (id integer, empresa_id integer, grupo_id integer, cargo_id integer, nome text, cpf text, rg text, datanascimento text, dataadmissao text, datadesligamento text, ativo integer, created_at text)');
        $leg->table('colaboradores')->insert([
            'id' => 1, 'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'cargo_id' => null, 'nome' => 'João Entregador', 'cpf' => '111.222.333-44',
            'datanascimento' => '1990-05-01', 'dataadmissao' => '2020-01-10', 'ativo' => 1,
        ]);

        // O vínculo de login é REVERSO: users.colaborador_id aponta para cá.
        $user = \App\Models\User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $leg->statement('create table users (id integer, colaborador_id integer, email text)');
        $leg->table('users')->insert(['id' => $user->id, 'colaborador_id' => 1, 'email' => 'joao01']);

        // Telefone vem da tabela filha (o colaborador não tem a coluna).
        $leg->statement('create table colaboradortelefones (id integer, colaborador_id integer, telefone text)');
        $leg->table('colaboradortelefones')->insert(['id' => 1, 'colaborador_id' => 1, 'telefone' => '(42) 99999-0000']);

        // Parentesco é FK no legado.
        $leg->statement('create table parentescos (id integer, descricao text)');
        $leg->table('parentescos')->insert(['id' => 3, 'descricao' => 'Cônjuge']);
        $leg->statement('create table colaboradorfamilias (id integer, colaborador_id integer, nome text, parentesco_id integer, datanascimento text)');
        $leg->table('colaboradorfamilias')->insert([
            'id' => 1, 'colaborador_id' => 1, 'nome' => 'Maria', 'parentesco_id' => 3, 'datanascimento' => '1992-03-03',
        ]);

        (new RhMigrator)->migrar($this->ctx());

        $col = Colaborador::withoutTenant()->find(1);
        $this->assertNotNull($col);
        $this->assertSame('João Entregador', $col->nome);
        $this->assertSame('11122233344', $col->cpf);
        $this->assertSame($user->id, (int) $col->user_id);
        $this->assertSame('(42) 99999-0000', $col->telefone);

        // Filha herda empresa_id do pai (F02 tenantParent) e resolve o parentesco.
        $fam = ColaboradorFamilia::query()->find(1);
        $this->assertNotNull($fam);
        $this->assertSame($empresa->id, (int) $fam->empresa_id);
        $this->assertSame('Cônjuge', $fam->parentesco);
    }

    public function test_frota_migra_veiculo_e_abastecimento(): void
    {
        $empresa = $this->empresa();
        $leg = DB::connection($this->legadoConn);

        // Schema REAL: abastecimento tem kmatual/totallitros (sem valor/tanque cheio).
        $leg->statement('create table veiculos (id integer, empresa_id integer, grupo_id integer, veiculotipo_id integer, tipocombustivel_id integer, placa text, descricao text, kmatual real, kmtrocaoleo real, kmultimatrocaoleo real, ativo integer)');
        $leg->table('veiculos')->insert(['id' => 7, 'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'placa' => 'ABC1D23', 'descricao' => 'Caminhão GLP', 'kmatual' => 12345, 'ativo' => 1]);
        $leg->statement('create table veiculoabastecimentos (id integer, veiculo_id integer, empresa_id integer, data text, kmatual real, kmanterior real, kmrodado real, totallitros real, mediaconsumo real)');
        $leg->table('veiculoabastecimentos')->insert(['id' => 3, 'veiculo_id' => 7, 'empresa_id' => $empresa->id, 'data' => '2026-01-05', 'kmatual' => 12300, 'totallitros' => 40.0]);

        (new FrotaMigrator)->migrar($this->ctx());

        $v = Veiculo::withoutTenant()->find(7);
        $this->assertNotNull($v);
        $this->assertSame('ABC1D23', $v->placa);
        $this->assertEqualsWithDelta(12345, (float) $v->km_atual, 0.01);

        $ab = VeiculoAbastecimento::query()->find(3);
        $this->assertNotNull($ab);
        $this->assertSame($empresa->id, (int) $ab->empresa_id);
        $this->assertEqualsWithDelta(12300, (float) $ab->km, 0.01);
        $this->assertEqualsWithDelta(40.0, (float) $ab->litros, 0.01);
    }

    public function test_crm_migra_promocao_e_pesquisa_de_posvenda_com_questionario(): void
    {
        $empresa = $this->empresa();
        $cliente = \App\Models\Cliente\Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        $leg = DB::connection($this->legadoConn);

        $leg->statement('create table promocoes (id integer, grupo_id integer, descricao text, datainicio text, datafim text, descontopercentual real, ativo integer)');
        $leg->table('promocoes')->insert(['id' => 2, 'grupo_id' => $empresa->grupo_id, 'descricao' => 'Black Friday', 'datainicio' => '2026-11-01', 'datafim' => '2026-11-30', 'descontopercentual' => 10.0, 'ativo' => 1]);

        // Schema REAL do pós-venda: campanha → perguntas → respostas possíveis;
        // a PESQUISA registra a resposta dada — e vira a linha de pos_vendas.
        $leg->statement('create table posvendas (id integer, grupo_id integer, empresa_id integer, descricao text, ativo integer)');
        $leg->table('posvendas')->insert(['id' => 1, 'grupo_id' => $empresa->grupo_id, 'empresa_id' => $empresa->id, 'descricao' => 'Pesquisa entrega', 'ativo' => 1]);
        $leg->statement('create table posvendaperguntas (id integer, posvenda_id integer, descricao text)');
        $leg->table('posvendaperguntas')->insert(['id' => 10, 'posvenda_id' => 1, 'descricao' => 'Atendimento']);
        $leg->statement('create table posvendarespostas (id integer, posvendapergunta_id integer, descricao text)');
        $leg->table('posvendarespostas')->insert(['id' => 100, 'posvendapergunta_id' => 10, 'descricao' => 'Ótimo']);
        $leg->statement('create table posvendapesquisas (id integer, cliente_id integer, setor_id integer, pedido_id integer, posvenda_id integer, datahora text, observacao text)');
        $leg->table('posvendapesquisas')->insert(['id' => 5, 'cliente_id' => $cliente->id, 'posvenda_id' => 1, 'datahora' => '2026-02-01 10:00:00', 'observacao' => 'cliente satisfeito']);
        $leg->statement('create table posvendapesquisarespostas (id integer, posvendapesquisa_id integer, posvendaresposta_id integer)');
        $leg->table('posvendapesquisarespostas')->insert(['id' => 1, 'posvendapesquisa_id' => 5, 'posvendaresposta_id' => 100]);

        (new CrmMigrator)->migrar($this->ctx());

        $promo = Promocao::withoutGrupo()->find(2);
        $this->assertNotNull($promo);
        $this->assertSame('Black Friday', $promo->descricao);
        $this->assertSame($empresa->grupo_id, (int) $promo->grupo_id);

        $pos = PosVenda::withoutTenant()->find(5);
        $this->assertNotNull($pos);
        $this->assertSame($empresa->id, (int) $pos->empresa_id);
        $this->assertSame('Pesquisa entrega', $pos->canal);
        // O questionário respondido é preservado na observação.
        $this->assertStringContainsString('Atendimento: Ótimo', (string) $pos->observacao);
        $this->assertStringContainsString('cliente satisfeito', (string) $pos->observacao);
    }

    public function test_pagamento_migra_cartao_com_taxa(): void
    {
        $empresa = $this->empresa();
        $leg = DB::connection($this->legadoConn);

        $leg->statement('create table cartaotransacoes (id integer, empresa_id integer, bandeira text, tipo text, nsu text, valorbruto real, taxapercentual real, valorliquido real, situacao text)');
        $leg->table('cartaotransacoes')->insert(['id' => 1, 'empresa_id' => $empresa->id, 'bandeira' => 'VISA', 'tipo' => 'credito', 'nsu' => '999', 'valorbruto' => 100.0, 'taxapercentual' => 3.0, 'valorliquido' => 97.0, 'situacao' => 'aprovada']);

        (new PagamentoMigrator)->migrar($this->ctx());

        $tx = CartaoTransacao::withoutTenant()->find(1);
        $this->assertNotNull($tx);
        $this->assertSame('VISA', $tx->bandeira);
        $this->assertEqualsWithDelta(97.0, (float) $tx->valor_liquido, 0.01);
    }

    public function test_registry_inclui_novos_migrators_sem_ciclo(): void
    {
        $nomes = array_map(fn ($m) => $m->nome(), MigratorRegistry::resolved());

        foreach (['users', 'rh', 'frota', 'crm', 'gestao', 'pagamentos', 'fiscal-config'] as $n) {
            $this->assertContains($n, $nomes, "Migrator {$n} não registrado.");
        }
        // dependências resolvidas: empresas antes de rh; users antes de pedidos.
        $this->assertLessThan(array_search('rh', $nomes, true), array_search('empresas', $nomes, true));
        $this->assertLessThan(array_search('pedidos', $nomes, true), array_search('users', $nomes, true));
    }

    public function test_dry_run_nao_grava(): void
    {
        $empresa = $this->empresa();
        $leg = DB::connection($this->legadoConn);
        $leg->statement('create table veiculos (id integer, empresa_id integer, grupo_id integer, placa text, kmatual real, ativo integer)');
        $leg->table('veiculos')->insert(['id' => 1, 'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'placa' => 'XYZ', 'kmatual' => 1, 'ativo' => 1]);

        (new FrotaMigrator)->migrar(new MigrationContext(dryRun: true, conexaoLegado: $this->legadoConn));

        $this->assertSame(0, Veiculo::withoutTenant()->count());
    }
}
