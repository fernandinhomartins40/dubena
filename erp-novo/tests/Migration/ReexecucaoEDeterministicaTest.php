<?php

namespace Tests\Migration;

use App\Etl\Migrators\EstadosMigrator;
use App\Etl\Support\MigrationContext;
use App\Models\Estado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F8-07 — *"repetir do zero; o segundo resultado deve ser determinístico e
 * idempotente"*.
 *
 * ## Por que isto precisa de teste próprio
 *
 * A idempotência **está implementada** — `PreservaIdsDoLegado` escreve por
 * `upsert`, e vários migradores comentam a decisão. O que faltava era a
 * **prova**: todo teste de migrador rodava a carga **uma vez**.
 *
 * É o padrão que se repetiu a rodada inteira nesta base: a estrutura existia e
 * era boa; o que faltava era alguém verificar.
 *
 * ## O que "idempotente" precisa significar aqui
 *
 * Não basta "não explode na segunda vez". O ensaio do F8 exige que a segunda
 * execução produza o **mesmo estado**:
 *
 *  - a mesma quantidade de linhas — senão a recarga duplica;
 *  - **os mesmos ids** — e este é o que dói. Id que muda entre execuções quebra
 *    toda referência já registrada: linhagem da conversão, `erp_id` do app,
 *    qualquer coisa que tenha guardado o número. O dado "está lá" e aponta para
 *    outra linha;
 *  - o mesmo conteúdo, campo a campo.
 *
 * ## Por que `EstadosMigrator`
 *
 * É o único que roda sem banco legado (usa o conjunto-semente de 27 UFs), então
 * exercita o caminho real de gravação — `upsert`, invariantes, contagem — sem
 * depender de uma origem que a suíte não tem. A propriedade testada é do
 * mecanismo de escrita, que é compartilhado.
 */
class ReexecucaoEDeterministicaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array<string, mixed>> */
    private function retratoDeEstados(): array
    {
        return DB::table('estados')
            ->orderBy('id')
            ->get()
            ->map(fn ($l) => (array) $l)
            ->all();
    }

    /**
     * Rodar duas vezes não duplica nem muda id.
     *
     * Id que muda é o defeito mais caro: a linhagem da conversão, o `erp_id` do
     * app e qualquer referência já gravada passam a apontar para outra linha —
     * sem erro, sem log, sem ninguém notar até alguém abrir um cadastro errado.
     */
    public function test_a_segunda_execucao_produz_o_mesmo_estado(): void
    {
        $migrator = new EstadosMigrator;
        $ctx = new MigrationContext;

        $primeira = $migrator->migrar($ctx);
        $retratoUm = $this->retratoDeEstados();

        $this->assertNotEmpty($retratoUm, 'a primeira carga precisa ter gravado algo — senão o teste compara dois vazios');

        $segunda = $migrator->migrar($ctx);
        $retratoDois = $this->retratoDeEstados();

        $this->assertSame(
            $primeira->lidos,
            $segunda->lidos,
            'a segunda leitura tem de ver a mesma origem',
        );

        $this->assertSame(
            count($retratoUm),
            count($retratoDois),
            'reexecutar não pode duplicar linha — é o que o `upsert` existe para impedir',
        );

        $this->assertSame(
            array_column($retratoUm, 'id'),
            array_column($retratoDois, 'id'),
            'os ids têm de ser os MESMOS: id que muda quebra toda referência já registrada',
        );

        $this->assertEquals(
            $retratoUm,
            $retratoDois,
            'o conteúdo tem de ser idêntico campo a campo',
        );
    }

    /**
     * As invariantes continuam passando depois da segunda carga.
     *
     * Uma recarga que passa na primeira e reprova na segunda é pior que uma que
     * falha sempre: dá confiança no ensaio e quebra no cutover, que é a única
     * execução que não dá para repetir.
     */
    public function test_invariantes_passam_depois_de_reexecutar(): void
    {
        $migrator = new EstadosMigrator;
        $ctx = new MigrationContext;

        $migrator->migrar($ctx);
        $migrator->migrar($ctx);

        $invariantes = $migrator->invariantes();

        $this->assertNotEmpty(
            $invariantes,
            'migrador sem invariante não prova nada — e este teste passaria vazio',
        );

        foreach ($invariantes as $invariante) {
            $resultado = $invariante->verificar($ctx);

            // `ok` já é falso para INCONCLUSIVA (F7-10): invariante que não pôde
            // ser verificada não é aprovação. Asserir o estado, e não a
            // mensagem, é o que aquela correção ensinou.
            $this->assertTrue(
                $resultado->ok,
                "invariante '{$resultado->invariante}' não passou depois da segunda carga ".
                "({$resultado->situacao}): {$resultado->mensagem}",
            );
        }
    }

    /**
     * Edição feita no sistema novo **é sobrescrita** pela recarga.
     *
     * Isto não é defeito — é a razão de existir a trava que impede `etl:run` sem
     * `--dry-run` num banco com dado criado aqui. O teste fixa o comportamento
     * para que ninguém "conserte" o upsert achando que preserva edição: ele
     * preserva o ID, não o conteúdo.
     *
     * Quem precisa dos dois é a quarentena, não o migrador.
     */
    public function test_recarga_sobrescreve_edicao_local_e_isso_e_esperado(): void
    {
        $migrator = new EstadosMigrator;
        $ctx = new MigrationContext;

        $migrator->migrar($ctx);

        $estado = Estado::query()->orderBy('id')->firstOrFail();
        $descricaoOriginal = (string) $estado->descricao;

        DB::table('estados')->where('id', $estado->id)->update(['descricao' => 'EDITADO NO SISTEMA NOVO']);

        $migrator->migrar($ctx);

        $this->assertSame(
            $descricaoOriginal,
            (string) DB::table('estados')->where('id', $estado->id)->value('descricao'),
            'a recarga SOBRESCREVE o que foi editado aqui — é por isso que existe a trava do `etl:run`',
        );
    }
}
