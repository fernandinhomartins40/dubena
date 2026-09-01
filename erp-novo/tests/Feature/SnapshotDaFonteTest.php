<?php

namespace Tests\Feature;

use App\Etl\Support\SnapshotDaFonte;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * F7-03 — retrato da fonte legada.
 *
 * A pergunta que o snapshot responde é *a fonte mudou entre o ensaio e o
 * cutover?*. Sem ela, um ensaio bem-sucedido na sexta não diz nada sobre a
 * virada no domingo: 300 clientes editados no sábado passariam sem que ninguém
 * percebesse que o ensaio validou outro estado.
 *
 * ## O que estes testes NÃO cobrem, e por quê
 *
 * `lob_integral` e "carga nova nunca derruba a última boa" exigem área de
 * *staging*, que este ETL não tem — ele lê a conexão `legado` ao vivo. O campo
 * fica declarado e **falso** para que o gate consiga reprovar; campo ausente
 * seria lido como "não se aplica", e é o oposto.
 */
class SnapshotDaFonteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A "fonte" aqui é uma tabela no próprio banco de teste.
     *
     * O que se testa é a lógica do retrato — schema, contagem, hash, watermark,
     * diff —, e ela não muda com o dialeto da origem.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('fonte_faz_de_conta', function ($t) {
            $t->integer('id');
            $t->string('nome');
            $t->string('atualizado_em')->nullable();
        });
    }

    private function fonte(): ConnectionInterface
    {
        return DB::connection();
    }

    private function snap(): SnapshotDaFonte
    {
        return app(SnapshotDaFonte::class);
    }

    private function semear(): void
    {
        DB::table('fonte_faz_de_conta')->insert([
            ['id' => 1, 'nome' => 'Dona Maria', 'atualizado_em' => '2026-01-01'],
            ['id' => 2, 'nome' => 'Seu João', 'atualizado_em' => '2026-01-02'],
        ]);
    }

    public function test_o_retrato_guarda_schema_contagem_e_watermark(): void
    {
        $this->semear();

        $id = $this->snap()->registrar($this->fonte(), 'oracle', 'fonte_faz_de_conta', 'atualizado_em');

        $this->assertNotNull($id);

        $linha = DB::table('conversao_snapshots')->find($id);

        $this->assertSame(2, (int) $linha->linhas);
        $this->assertSame('2026-01-02', $linha->watermark_valor);
        $this->assertNotNull($linha->hash_conteudo);

        $colunas = json_decode((string) $linha->colunas, true);
        $this->assertArrayHasKey('nome', $colunas, 'o manifesto é NOMINAL: coluna que some tem de aparecer no diff');
    }

    /**
     * `lob_integral` é falso, e isso é a entrega — não a omissão.
     *
     * O gate de cutover precisa conseguir reprovar enquanto não houver staging.
     * Um campo ausente seria lido como "não se aplica".
     */
    public function test_lob_integral_fica_declarado_e_falso(): void
    {
        $id = $this->snap()->registrar($this->fonte(), 'oracle', 'fonte_faz_de_conta');

        $this->assertFalse(
            (bool) DB::table('conversao_snapshots')->find($id)->lob_integral,
            'sem staging não há LOB integral, e o gate precisa poder ver isso',
        );
    }

    /**
     * Duas leituras sem mudança dão o mesmo hash.
     *
     * Se não desse, o alarme dispararia sozinho — e alarme que dispara sozinho é
     * alarme que se aprende a ignorar, justamente quando ele estiver certo.
     */
    public function test_fonte_parada_produz_o_mesmo_hash(): void
    {
        $this->semear();

        $a = $this->snap()->registrar($this->fonte(), 'oracle', 'fonte_faz_de_conta');
        $b = $this->snap()->registrar($this->fonte(), 'oracle', 'fonte_faz_de_conta');

        $this->assertSame(
            DB::table('conversao_snapshots')->find($a)->hash_conteudo,
            DB::table('conversao_snapshots')->find($b)->hash_conteudo,
        );

        $this->assertSame([], $this->snap()->diferencas('oracle', 'fonte_faz_de_conta'));
    }

    /**
     * O caso que o snapshot existe para pegar: linha EDITADA.
     *
     * A contagem não muda, o watermark pode não mudar — só o conteúdo muda. É o
     * defeito mais fácil de passar batido no cutover, e o hash é a única coisa
     * que o vê.
     */
    public function test_linha_editada_e_detectada_mesmo_sem_mudar_a_contagem(): void
    {
        $this->semear();
        $this->snap()->registrar($this->fonte(), 'oracle', 'fonte_faz_de_conta');

        DB::table('fonte_faz_de_conta')->where('id', 1)->update(['nome' => 'Maria da Silva']);

        $this->snap()->registrar($this->fonte(), 'oracle', 'fonte_faz_de_conta');

        $diff = $this->snap()->diferencas('oracle', 'fonte_faz_de_conta');

        $this->assertNotEmpty($diff, 'edição sem mudança de contagem é o caso que mais importa');
        $this->assertStringContainsString('conteúdo alterado', implode(' ', $diff));
    }

    public function test_linha_nova_aparece_na_contagem(): void
    {
        $this->semear();
        $this->snap()->registrar($this->fonte(), 'oracle', 'fonte_faz_de_conta', 'atualizado_em');

        DB::table('fonte_faz_de_conta')->insert(['id' => 3, 'nome' => 'Novo', 'atualizado_em' => '2026-02-01']);
        $this->snap()->registrar($this->fonte(), 'oracle', 'fonte_faz_de_conta', 'atualizado_em');

        $diff = implode(' ', $this->snap()->diferencas('oracle', 'fonte_faz_de_conta'));

        $this->assertStringContainsString('linhas 2 → 3', $diff);
        $this->assertStringContainsString('watermark', $diff);
    }

    /**
     * Coluna que SOME é o defeito mais silencioso da conversão.
     *
     * O migrador lê `null` e grava `null`, sem erro nenhum. Só a comparação
     * nominal do schema o vê.
     */
    public function test_coluna_que_some_da_fonte_aparece_no_diff(): void
    {
        $this->semear();
        $this->snap()->registrar($this->fonte(), 'oracle', 'fonte_faz_de_conta');

        Schema::table('fonte_faz_de_conta', fn ($t) => $t->dropColumn('atualizado_em'));

        $this->snap()->registrar($this->fonte(), 'oracle', 'fonte_faz_de_conta');

        $diff = implode(' ', $this->snap()->diferencas('oracle', 'fonte_faz_de_conta'));

        $this->assertStringContainsString("coluna 'atualizado_em' SUMIU", $diff);
    }

    /**
     * Um retrato só não é comparação.
     *
     * Devolver "sem diferenças" com um único snapshot faria a primeira execução
     * parecer uma confirmação de que nada mudou — e é justamente a execução que
     * não tem com o que comparar.
     */
    public function test_um_retrato_so_nao_afirma_estabilidade(): void
    {
        $this->semear();
        $this->snap()->registrar($this->fonte(), 'oracle', 'fonte_faz_de_conta');

        $this->assertSame([], $this->snap()->diferencas('oracle', 'fonte_faz_de_conta'));
        $this->assertSame(1, DB::table('conversao_snapshots')->count());
    }

    /**
     * Hash ausente é ausência de MEDIÇÃO, não igualdade.
     *
     * Dois retratos que falharam ao hashear dariam `null !== null` = falso, e o
     * comparador afirmaria "conteúdo igual" sobre uma tabela que ninguém
     * conseguiu ler — liberando o cutover com base numa comparação que não
     * aconteceu.
     */
    public function test_hash_ausente_nao_passa_por_conteudo_igual(): void
    {
        $this->semear();
        $this->snap()->registrar($this->fonte(), 'oracle', 'fonte_faz_de_conta');
        $this->snap()->registrar($this->fonte(), 'oracle', 'fonte_faz_de_conta');

        // Simula as duas leituras de hash tendo falhado.
        DB::table('conversao_snapshots')->update(['hash_conteudo' => null]);

        $diff = implode(' ', $this->snap()->diferencas('oracle', 'fonte_faz_de_conta'));

        $this->assertStringContainsString('NÃO verificado', $diff);
    }

    /** Tabela inexistente devolve nulo — não explode, e não finge retrato. */
    public function test_tabela_ilegivel_devolve_nulo(): void
    {
        $this->assertNull($this->snap()->registrar($this->fonte(), 'oracle', 'nao_existe_essa_tabela'));
        $this->assertSame(0, DB::table('conversao_snapshots')->count());
    }

    /**
     * O comando reprova quando não achou tabela nenhuma.
     *
     * Snapshot de zero tabelas não é snapshot limpo — é snapshot que não
     * aconteceu. Esta base já pagou por isso duas vezes (registry vazio dizendo
     * "ETL concluído", teste que varria zero arquivos e passava).
     */
    public function test_o_comando_reprova_snapshot_de_zero_tabelas(): void
    {
        $this->artisan('conversao:snapshot', [
            '--conexao' => config('database.default'),
            '--tabela' => ['nao_existe_essa_tabela'],
        ])->assertFailed();
    }

    /** Conexão inexistente reprova, e diz qual. */
    public function test_o_comando_reprova_conexao_invalida(): void
    {
        $this->artisan('conversao:snapshot', ['--conexao' => 'nao_existe'])->assertFailed();
    }

    /**
     * O defeito que teste verde não pegaria — e que eu quase deixei passar.
     *
     * `--comparar` na PRIMEIRA execução não tem retrato anterior. Sem esta
     * verificação, ele imprimia "a fonte não mudou desde o retrato anterior" e
     * retornava sucesso — **sem nunca ter comparado com nada**.
     *
     * Um script de cutover leria esse sucesso como permissão para virar. É a
     * mesma família de defeito que esta base já pagou duas vezes: registry vazio
     * dizendo "ETL concluído", teste que varria zero arquivos e passava.
     * Ausência de dado passando por confirmação.
     */
    public function test_comparar_na_primeira_leitura_reprova_em_vez_de_dizer_que_nada_mudou(): void
    {
        $this->semear();

        $this->artisan('conversao:snapshot', [
            '--conexao' => config('database.default'),
            '--tabela' => ['fonte_faz_de_conta'],
            '--comparar' => true,
        ])
            ->doesntExpectOutputToContain('A fonte não mudou')
            ->assertFailed();
    }

    /** Com dois retratos e fonte parada, aí sim aprova. */
    public function test_comparar_aprova_quando_ha_anterior_e_nada_mudou(): void
    {
        $this->semear();

        $opcoes = ['--conexao' => config('database.default'), '--tabela' => ['fonte_faz_de_conta']];

        $this->artisan('conversao:snapshot', $opcoes)->assertSuccessful();

        $this->artisan('conversao:snapshot', $opcoes + ['--comparar' => true])
            ->expectsOutputToContain('A fonte não mudou')
            ->assertSuccessful();
    }

    /** `--comparar` reprova quando a fonte mudou: é o gate do cutover. */
    public function test_comparar_reprova_quando_a_fonte_mudou(): void
    {
        $this->semear();

        $this->artisan('conversao:snapshot', [
            '--conexao' => config('database.default'),
            '--tabela' => ['fonte_faz_de_conta'],
        ])->assertSuccessful();

        DB::table('fonte_faz_de_conta')->where('id', 1)->update(['nome' => 'Editado depois do ensaio']);

        $this->artisan('conversao:snapshot', [
            '--conexao' => config('database.default'),
            '--tabela' => ['fonte_faz_de_conta'],
            '--comparar' => true,
        ])->assertFailed();
    }
}
