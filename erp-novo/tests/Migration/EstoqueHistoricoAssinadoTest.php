<?php

namespace Tests\Migration;

use App\Domain\Estoque\ConferenciaDeSaldo;
use App\Etl\Migrators\EstoqueMigrator;
use App\Etl\Support\MigrationContext;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Produto\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * O ledger da aplicação tem quantidade ASSINADA: `Σ estoquehistorico.quantidade
 * = estoquesaldos.quantidade` é o contrato do `EstoqueService`.
 *
 * O legado guarda o módulo e o sentido em `movimentacao`. O migrador copiava o
 * módulo, e a carga real (2026-10-11) fez `estoque:conferir` acusar 121
 * divergências — um setor com 3.484 botijões "somava" 2,38 milhões. Com o sinal,
 * 89 dos 121 pares fecham; o resto é diferença que já existia no legado.
 */
class EstoqueHistoricoAssinadoTest extends TestCase
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

    public function test_saida_do_legado_entra_negativa_e_o_ledger_fecha_com_o_saldo(): void
    {
        $empresa = Empresa::factory()->create();
        $setor = Setor::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $fecha = Produto::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $naoFecha = Produto::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $leg = DB::connection($this->legadoConn);
        $leg->statement('create table setors (id integer, empresa_id integer, grupo_id integer, descricao text, ativo text)');
        $leg->statement('create table estoquesetors (id integer, empresa_id integer, setor_id integer, produto_id integer, quantidade real)');
        $leg->statement('create table estoquesetorhistoricos (id integer, empresa_id integer, setor_id integer, produto_id integer, movimentacao text, quantidade real, datahora text)');

        $leg->table('setors')->insert([
            'id' => $setor->id, 'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'descricao' => 'Deposito', 'ativo' => '1',
        ]);
        $leg->table('estoquesetors')->insert([
            ['id' => 1, 'empresa_id' => $empresa->id, 'setor_id' => $setor->id, 'produto_id' => $fecha->id, 'quantidade' => 70],
            // Saldo que o histórico NÃO explica — existe no dump real.
            ['id' => 2, 'empresa_id' => $empresa->id, 'setor_id' => $setor->id, 'produto_id' => $naoFecha->id, 'quantidade' => 50],
        ]);
        $base = ['empresa_id' => $empresa->id, 'setor_id' => $setor->id];
        $leg->table('estoquesetorhistoricos')->insert([
            $base + ['id' => 11, 'produto_id' => $fecha->id, 'movimentacao' => 'ENTRADA', 'quantidade' => 100, 'datahora' => '2026-01-01 08:00:00'],
            $base + ['id' => 12, 'produto_id' => $fecha->id, 'movimentacao' => 'SAIDA', 'quantidade' => 30, 'datahora' => '2026-01-02 08:00:00'],
            $base + ['id' => 13, 'produto_id' => $naoFecha->id, 'movimentacao' => 'ENTRADA', 'quantidade' => 10, 'datahora' => '2026-01-03 08:00:00'],
        ]);

        $resultado = (new EstoqueMigrator)->migrar(new MigrationContext(conexaoLegado: $this->legadoConn));

        $this->assertSame(-30.0, (float) DB::table('estoquehistorico')->where('id', 12)->value('quantidade'));
        $this->assertSame(100.0, (float) DB::table('estoquehistorico')->where('id', 11)->value('quantidade'));
        $this->assertSame(70.0, (float) DB::table('estoquehistorico')->where('id', 12)->value('saldo_resultante'));

        // A conferência da própria aplicação é o juiz: só o par que o legado
        // já trazia quebrado pode sobrar.
        $divergencias = app(ConferenciaDeSaldo::class)->divergencias($empresa->id);
        $this->assertSame([$naoFecha->id], $divergencias->pluck('produto_id')->all());

        // E essa sobra é dita na hora, não descoberta depois.
        $this->assertStringContainsString('1 de 2 saldo(s)', implode(' | ', $resultado->avisos));
    }
}
