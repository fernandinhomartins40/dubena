<?php

namespace Tests\Migration;

use App\Etl\Invariants\CountInvariant;
use App\Etl\Support\MigrationContext;
use App\Models\Grupo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GATE da T2.4 — `CountInvariant` com descartes E acréscimos.
 *
 * Antes só existia o lado dos descartes. Migrators que criam linhas a partir de
 * uma SEGUNDA origem (pedidos e clientes vindos do app, no
 * `AppGasEmCasaMigrator`) eram estruturalmente incapazes de passar — e o efeito
 * grave não foi o falso negativo isolado: falhas legítimas e falhas por desenho
 * ficavam indistinguíveis no mesmo placar vermelho. Foi assim que a duplicação
 * 4× de clientes virou ruído de fundo e passou despercebida.
 *
 * O risco do remédio é virar passe-livre. Por isso os testes abaixo cobrem os
 * dois lados: o ajuste legítimo passa, e um destino inflado além do ajuste
 * continua falhando.
 */
class CountInvariantAjustesTest extends TestCase
{
    use RefreshDatabase;

    private string $legadoConn = 'legado_teste_count';

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

    /** Cria origem e destino com N e M linhas, usando uma tabela real do schema novo. */
    private function popular(int $origem, int $destino): void
    {
        $grupo = Grupo::create(['descricao' => 'G', 'ativo' => true]);
        // `cidades.uf` referencia `estados.uf`.
        DB::table('estados')->insertOrIgnore([
            'id' => 41, 'uf' => 'PR', 'descricao' => 'Paraná', 'cod_ibge' => 41,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $leg = DB::connection($this->legadoConn);
        $leg->statement('create table cidades (id integer, descricao text, uf text)');

        for ($i = 1; $i <= $origem; $i++) {
            $leg->table('cidades')->insert(['id' => $i, 'descricao' => "Cidade {$i}", 'uf' => 'PR']);
        }

        for ($i = 1; $i <= $destino; $i++) {
            DB::table('cidades')->insert([
                'id' => $i, 'grupo_id' => $grupo->id, 'descricao' => "Cidade {$i}", 'uf' => 'PR',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function test_passa_com_descartes_e_acrescimos_combinados(): void
    {
        // origem=100, descartes=5, acrescimos=10 → esperado 105 no destino.
        $this->popular(origem: 100, destino: 105);

        $r = (new CountInvariant(
            $this->ctx(), 'cidades', 'cidades',
            descartesEsperados: 5,
            acrescimosEsperados: 10,
        ))->verificar();

        $this->assertTrue($r->ok, "deveria passar, obteve: {$r->mensagem}");
        $this->assertStringContainsString('descartes=5', $r->mensagem);
        $this->assertStringContainsString('acrescimos=10', $r->mensagem);
    }

    public function test_acrescimo_nao_e_passe_livre(): void
    {
        // origem=100, acrescimos=10 → esperado 110; destino tem 200.
        $this->popular(origem: 100, destino: 200);

        $r = (new CountInvariant(
            $this->ctx(), 'cidades', 'cidades',
            acrescimosEsperados: 10,
        ))->verificar();

        $this->assertFalse($r->ok, 'destino inflado além do acréscimo declarado tem de FALHAR');
    }

    public function test_ajuste_aceita_closure_calculada_sobre_os_dados(): void
    {
        $this->popular(origem: 100, destino: 112);

        // A closure é o formato preferido: recalcula a cada execução em vez de
        // petrificar um número medido uma única vez.
        $r = (new CountInvariant(
            $this->ctx(), 'cidades', 'cidades',
            acrescimosEsperados: fn () => DB::table('cidades')->where('id', '>', 100)->count(),
        ))->verificar();

        $this->assertTrue($r->ok, "closure deveria resolver o acréscimo, obteve: {$r->mensagem}");
    }

    public function test_tabela_ausente_no_legado_continua_falhando(): void
    {
        // A checagem `hasTable` é a correção mais valiosa do pipeline: foi ela
        // que expôs o PagamentoMigrator lendo tabelas inventadas (T2.3).
        // Acréscimo nenhum pode encobri-la.
        $r = (new CountInvariant(
            $this->ctx(), 'tabela_que_nao_existe', 'cidades',
            acrescimosEsperados: 9999,
        ))->verificar();

        $this->assertFalse($r->ok);
        $this->assertStringContainsString('NÃO existe no legado', $r->mensagem);
    }

    public function test_origem_inacessivel_e_inconclusiva_e_falha(): void
    {
        $ctx = new MigrationContext(conexaoLegado: 'conexao_que_nao_existe');
        $r = (new CountInvariant($ctx, 'cidades', 'cidades'))->verificar();

        // Continua BLOQUEANDO: não verificado nunca é aprovação.
        $this->assertFalse($r->ok);

        // F7-10 — a asserção passou do texto da mensagem para o ESTADO.
        //
        // Antes a mensagem carregava a palavra "inconclusiva"; agora existe um
        // estado próprio, e verificar o estado é mais forte — a mensagem pode
        // ser reescrita sem que a semântica mude, e o teste não deve quebrar
        // por isso (foi o que aconteceu quando a distinção foi introduzida).
        $this->assertTrue($r->naoVerificada(), 'origem inacessível não foi verificada — não é reprovação');
        $this->assertStringContainsString('indisponível', $r->mensagem);
    }
}
