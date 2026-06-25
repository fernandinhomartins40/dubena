<?php

namespace Tests\Migration;

use App\Etl\MigratorRegistry;
use App\Etl\Migrators\CrmMigrator;
use App\Etl\Migrators\FrotaMigrator;
use App\Etl\Migrators\PagamentoMigrator;
use App\Etl\Migrators\RhMigrator;
use App\Etl\Support\MigrationContext;
use App\Models\Crm\Promocao;
use App\Models\Crm\PosVenda;
use App\Models\Empresa;
use App\Models\Frota\Veiculo;
use App\Models\Frota\VeiculoAbastecimento;
use App\Models\Pagamento\CartaoTransacao;
use App\Models\Rh\Colaborador;
use App\Models\Rh\ColaboradorFamilia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * F15 — ETL da cauda longa (RH, frota, CRM, pagamentos). Simula o banco LEGADO
 * com uma conexão sqlite em memória populada com linhas no formato legado (nomes
 * snake-sem-underscore, flags '0'/'1'), e prova carga + transform + invariantes.
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

    public function test_rh_migra_colaboradores_e_familia_com_heranca_de_empresa(): void
    {
        $empresa = $this->empresa();
        $leg = DB::connection($this->legadoConn);

        $leg->statement('create table colaboradores (id integer, empresa_id integer, grupo_id integer, nome text, cpf text, datanascimento text, dataadmissao text, entregador integer, ativo integer)');
        $leg->table('colaboradores')->insert([
            'id' => 1, 'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'nome' => 'João Entregador', 'cpf' => '11122233344', 'datanascimento' => '1990-05-01',
            'dataadmissao' => '2020-01-10', 'entregador' => 1, 'ativo' => 1,
        ]);
        $leg->statement('create table colaboradorfamilias (id integer, colaborador_id integer, nome text, parentesco text, datanascimento text)');
        $leg->table('colaboradorfamilias')->insert([
            'id' => 1, 'colaborador_id' => 1, 'nome' => 'Maria', 'parentesco' => 'Cônjuge', 'datanascimento' => '1992-03-03',
        ]);

        (new RhMigrator)->migrar($this->ctx());

        $col = Colaborador::withoutTenant()->find(1);
        $this->assertNotNull($col);
        $this->assertSame('João Entregador', $col->nome);
        $this->assertTrue((bool) $col->entregador);
        $this->assertSame('1990-05-01', $col->data_nascimento?->format('Y-m-d') ?? (string) $col->data_nascimento);

        // Filha herda empresa_id do pai (F02 tenantParent), mesmo sem tenant ativo.
        $fam = ColaboradorFamilia::query()->find(1);
        $this->assertNotNull($fam);
        $this->assertSame($empresa->id, (int) $fam->empresa_id);
    }

    public function test_frota_migra_veiculo_e_abastecimento(): void
    {
        $empresa = $this->empresa();
        $leg = DB::connection($this->legadoConn);

        $leg->statement('create table veiculos (id integer, empresa_id integer, grupo_id integer, placa text, descricao text, kmatual real, ativo integer)');
        $leg->table('veiculos')->insert(['id' => 7, 'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'placa' => 'ABC1D23', 'descricao' => 'Caminhão GLP', 'kmatual' => 12345, 'ativo' => 1]);
        $leg->statement('create table veiculoabastecimentos (id integer, veiculo_id integer, data text, km real, litros real, valortotal real, tanquecheio integer)');
        $leg->table('veiculoabastecimentos')->insert(['id' => 3, 'veiculo_id' => 7, 'data' => '2026-01-05', 'km' => 12300, 'litros' => 40.0, 'valortotal' => 280.0, 'tanquecheio' => 1]);

        (new FrotaMigrator)->migrar($this->ctx());

        $v = Veiculo::withoutTenant()->find(7);
        $this->assertNotNull($v);
        $this->assertSame('ABC1D23', $v->placa);
        $this->assertEqualsWithDelta(12345, (float) $v->km_atual, 0.01);

        $ab = VeiculoAbastecimento::query()->find(3);
        $this->assertSame($empresa->id, (int) $ab->empresa_id);
        $this->assertTrue((bool) $ab->tanque_cheio);
    }

    public function test_crm_migra_promocao_grupo_e_posvenda_empresa(): void
    {
        $empresa = $this->empresa();
        $leg = DB::connection($this->legadoConn);

        $leg->statement('create table promocoes (id integer, grupo_id integer, descricao text, datainicio text, datafim text, descontopercentual real, ativo integer)');
        $leg->table('promocoes')->insert(['id' => 2, 'grupo_id' => $empresa->grupo_id, 'descricao' => 'Black Friday', 'datainicio' => '2026-11-01', 'datafim' => '2026-11-30', 'descontopercentual' => 10.0, 'ativo' => 1]);
        $leg->statement('create table posvendas (id integer, empresa_id integer, cliente_id integer, data text, nota integer, canal text, situacao text)');
        $leg->table('posvendas')->insert(['id' => 5, 'empresa_id' => $empresa->id, 'cliente_id' => null, 'data' => '2026-02-01', 'nota' => 9, 'canal' => 'telefone', 'situacao' => 'concluida']);

        (new CrmMigrator)->migrar($this->ctx());

        $promo = Promocao::withoutGrupo()->find(2);
        $this->assertNotNull($promo);
        $this->assertSame('Black Friday', $promo->descricao);
        $this->assertSame($empresa->grupo_id, (int) $promo->grupo_id);

        $pos = PosVenda::withoutTenant()->find(5);
        $this->assertSame(9, (int) $pos->nota);
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

        foreach (['rh', 'frota', 'crm', 'gestao', 'pagamentos'] as $n) {
            $this->assertContains($n, $nomes, "Migrator {$n} não registrado.");
        }
        // dependências resolvidas: empresas antes de rh/frota/pagamentos.
        $this->assertLessThan(array_search('rh', $nomes, true), array_search('empresas', $nomes, true));
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
