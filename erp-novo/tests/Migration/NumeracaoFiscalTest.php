<?php

namespace Tests\Migration;

use App\Domain\Fiscal\ModeloDocumento;
use App\Domain\Shared\NumeroSequencialService;
use App\Etl\Migrators\FiscalMigrator;
use App\Etl\Support\MigrationContext;
use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Numeração fiscal herdada do legado.
 *
 * **O risco que isto cobre é o mais caro do cutover.** A numeração de NF-e é
 * sequencial por empresa+modelo+série e a Receita já conhece os números
 * emitidos. Sem semear a sequência, a primeira nota emitida no sistema novo sai
 * com número 1 — colidindo com as 40.316 notas modelo 55 já autorizadas para a
 * matriz. A SEFAZ rejeita, e o erro só aparece na hora de faturar.
 *
 * `NumeroSequencialService::definir()` existia para este fim desde o início (o
 * docblock diz "ETL importando a numeração da empresa legada"), mas nenhum
 * migrator o chamava.
 */
class NumeracaoFiscalTest extends TestCase
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

    /** Cria as tabelas mínimas do legado que o FiscalMigrator lê. */
    private function schemaLegado(Empresa $empresa, int $nfeNumero, int $nfceNumero): void
    {
        $leg = DB::connection($this->legadoConn);

        $leg->statement('create table empresas (id integer, nfeserie integer, nfenumero integer, nfceserie integer, nfcenumero integer)');
        $leg->table('empresas')->insert([
            'id' => $empresa->id,
            'nfeserie' => 1, 'nfenumero' => $nfeNumero,
            'nfceserie' => 1, 'nfcenumero' => $nfceNumero,
        ]);

        $leg->statement('create table nfemitidas (id integer, grupo_id integer, empresa_id integer, cliente_id integer, chaveacesso text, nfmodelo text, nfserie text, nfnumero integer, datahoraemissao text, protocolo text, vprod real, vdesc real, vfrete real, vicms real, vst real, vipi real, vpis real, vcofins real, vnf real, nfsituacao_id integer, inutilizarcancelar text, created_at text)');
    }

    public function test_semeia_a_numeracao_a_partir_do_contador_da_empresa(): void
    {
        $empresa = Empresa::factory()->create();
        $this->schemaLegado($empresa, nfeNumero: 81074, nfceNumero: 361778);

        (new FiscalMigrator)->migrar($this->ctx());

        $sequencia = app(NumeroSequencialService::class);

        // O PRÓXIMO número tem de vir depois do último usado no legado.
        $this->assertSame(
            81075,
            $sequencia->proximo(ModeloDocumento::NFE->chaveSequencia($empresa->id, 1)),
            'a próxima NF-e repetiria um número já autorizado na Receita',
        );
        $this->assertSame(
            361779,
            $sequencia->proximo(ModeloDocumento::NFCE->chaveSequencia($empresa->id, 1)),
        );
    }

    /**
     * O caso real do dump: na empresa 2 o contador diz 81.074 e o maior número
     * REALMENTE emitido é 335.358 (o legado reiniciou a série em algum momento).
     * Seguir o contador cegamente faria a matriz emitir nota com número já usado.
     */
    public function test_contador_defasado_perde_para_o_maior_numero_emitido(): void
    {
        $empresa = Empresa::factory()->create();
        $this->schemaLegado($empresa, nfeNumero: 81074, nfceNumero: 0);

        // Uma nota já emitida com número MUITO acima do contador.
        DB::connection($this->legadoConn)->table('nfemitidas')->insert([
            'id' => 1, 'grupo_id' => $empresa->grupo_id, 'empresa_id' => $empresa->id,
            'nfmodelo' => '55', 'nfserie' => '1', 'nfnumero' => 335358,
            'chaveacesso' => str_repeat('1', 44), 'protocolo' => '123',
            'vnf' => 100, 'datahoraemissao' => '2026-01-01 10:00:00',
        ]);

        (new FiscalMigrator)->migrar($this->ctx());

        $this->assertSame(
            335359,
            app(NumeroSequencialService::class)
                ->proximo(ModeloDocumento::NFE->chaveSequencia($empresa->id, 1)),
            'adotou o contador defasado e repetiria notas já emitidas',
        );
    }

    /** Série nunca usada não deve nascer com número inventado. */
    public function test_serie_sem_uso_comeca_do_um(): void
    {
        $empresa = Empresa::factory()->create();
        $this->schemaLegado($empresa, nfeNumero: 0, nfceNumero: 0);

        (new FiscalMigrator)->migrar($this->ctx());

        $this->assertSame(
            1,
            app(NumeroSequencialService::class)
                ->proximo(ModeloDocumento::NFE->chaveSequencia($empresa->id, 1)),
        );
    }

    /** A recarga do ETL é idempotente: não pode empurrar a numeração adiante. */
    public function test_recarregar_nao_avanca_a_numeracao(): void
    {
        $empresa = Empresa::factory()->create();
        $this->schemaLegado($empresa, nfeNumero: 500, nfceNumero: 0);

        (new FiscalMigrator)->migrar($this->ctx());
        (new FiscalMigrator)->migrar($this->ctx());

        $this->assertSame(
            501,
            app(NumeroSequencialService::class)
                ->proximo(ModeloDocumento::NFE->chaveSequencia($empresa->id, 1)),
            'rodar o ETL duas vezes pulou faixa de numeração',
        );
    }
}
