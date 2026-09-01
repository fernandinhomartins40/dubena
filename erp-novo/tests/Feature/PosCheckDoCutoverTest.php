<?php

namespace Tests\Feature;

use App\Etl\Support\RegistroDaConversao;
use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F7-12 — o pós-check do cutover.
 *
 * ## Por que existe, se já há `cutover:check` e `golive:check`
 *
 * Os dois são **pré**-switch e respondem outras perguntas: *os dados batem com a
 * origem?* e *a configuração está pronta?*. Depois da virada a conexão legada
 * pode nem existir mais, e a pergunta muda para *a operação consegue
 * trabalhar?*.
 *
 * Estes testes fixam o que se mede depois — as coisas que, quebradas, param a
 * revenda em minutos e só aparecem com tráfego real.
 */
class PosCheckDoCutoverTest extends TestCase
{
    use RefreshDatabase;

    private function cargaConcluida(): void
    {
        $registro = app(RegistroDaConversao::class);
        $registro->iniciar('clientes', dryRun: false, comInvariantes: true);
        $registro->encerrar('CONCLUIDA', 'ok');
    }

    /** Um sistema recém-virado e sadio passa. */
    public function test_sistema_sadio_passa(): void
    {
        Empresa::factory()->create();
        $this->cargaConcluida();

        $this->artisan('cutover:pos-check')->assertSuccessful();
    }

    /**
     * Execução eternamente EM_ANDAMENTO reprova.
     *
     * Linha aberta é indistinguível de carga rodando agora — alguém vai esperar
     * por um processo que já morreu. É o motivo pelo qual `INTERROMPIDA` existe
     * como estado (F7-02).
     */
    public function test_execucao_sem_desfecho_reprova(): void
    {
        Empresa::factory()->create();

        // Uma carga BEM SUCEDIDA primeiro: sem ela, a verificação "a última
        // carga terminou CONCLUIDA" também reprovaria, e este teste passaria
        // sem exercitar o que promete.
        //
        // Descobri isso plantando a regressão: desativei a checagem de execução
        // aberta e nenhum teste falhou — o cenário estava sendo pego por outra
        // verificação, com uma mensagem que por acaso continha a mesma palavra.
        $this->cargaConcluida();

        // Agora a execução órfã, DEPOIS: é o cenário do processo morto.
        app(RegistroDaConversao::class)->iniciar('clientes', false, true);

        $this->artisan('cutover:pos-check')
            ->expectsOutputToContain('sem desfecho')
            ->assertFailed();
    }

    /** Carga que terminou FALHOU não pode passar como cutover sadio. */
    public function test_ultima_carga_que_falhou_reprova(): void
    {
        Empresa::factory()->create();

        $registro = app(RegistroDaConversao::class);
        $registro->iniciar('clientes', false, true);
        $registro->encerrar('FALHOU', 'invariante reprovada');

        $this->artisan('cutover:pos-check')->assertFailed();
    }

    /**
     * Nenhuma execução registrada reprova.
     *
     * Ausência de registro não é ausência de problema: significa que o cutover
     * rodou sem deixar rastro, e não há como afirmar nada sobre ele. Passar aqui
     * seria o mesmo defeito do registry vazio imprimindo "ETL concluído".
     */
    public function test_sem_execucao_registrada_reprova(): void
    {
        Empresa::factory()->create();

        $this->artisan('cutover:pos-check')
            ->expectsOutputToContain('sem registro')
            ->assertFailed();
    }

    /** Quarentena pendente é dado que não entrou — e ninguém sabe qual. */
    public function test_quarentena_pendente_reprova(): void
    {
        Empresa::factory()->create();

        $registro = app(RegistroDaConversao::class);
        $registro->iniciar('clientes', false, true);
        $registro->quarentena('oracle', 'clientes', '77', 'OWNER_AMBIGUO', 'aparece em duas empresas');
        $registro->encerrar('CONCLUIDA', 'ok');

        $this->artisan('cutover:pos-check')->assertFailed();
    }

    /**
     * Empresa sem tenant AVISA — e não reprova.
     *
     * `app_tenant_can_read` compara o `tenant_account_id` da linha com o do
     * envelope: nulo nunca casa, e a revenda não enxerga o próprio dado.
     *
     * Mas a coluna é aditiva e quem a preenche é a conversão: num banco que
     * ainda não converteu, nulo é o estado NORMAL — inclusive o que
     * `Empresa::factory()` produz. Reprovar aqui faria o comando reprovar sempre
     * fora do cutover, e portão que sempre reprova é portão que se aprende a
     * ignorar. Quem trata isso como bloqueio é o `golive:check`.
     */
    public function test_empresa_sem_tenant_avisa_sem_reprovar(): void
    {
        $empresa = Empresa::factory()->create();
        DB::table('empresas')->where('id', $empresa->id)->update(['tenant_account_id' => null]);

        $this->cargaConcluida();

        $this->artisan('cutover:pos-check')
            ->expectsOutputToContain('invisíveis para a RLS')
            ->assertSuccessful();
    }

    /**
     * Banco sem empresa nenhuma reprova.
     *
     * Zero empresas passaria por "nenhuma sem tenant" numa verificação ingênua —
     * o vazio satisfaz a condição sem satisfazer a intenção. É a armadilha que
     * esta base já pagou com o registry vazio e com o teste que varria zero
     * arquivos.
     */
    public function test_banco_sem_empresa_reprova(): void
    {
        $this->cargaConcluida();

        $this->artisan('cutover:pos-check')
            ->expectsOutputToContain('não tem o que operar')
            ->assertFailed();
    }

    /** Job falhado depois do switch reprova: nota não emitida, boleto não registrado. */
    public function test_job_falhado_recente_reprova(): void
    {
        Empresa::factory()->create();
        $this->cargaConcluida();

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'estourou depois do switch',
            'failed_at' => now(),
        ]);

        $this->artisan('cutover:pos-check')->assertFailed();
    }

    /**
     * Job falhado ANTES da janela não reprova.
     *
     * O pós-check pergunta sobre o depois. Falha de ontem é história, e tratá-la
     * como impedimento faria o comando reprovar para sempre num banco com
     * qualquer passado — que é o mesmo que não ter comando.
     */
    public function test_job_falhado_antigo_nao_reprova(): void
    {
        Empresa::factory()->create();
        $this->cargaConcluida();

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'falha de ontem',
            'failed_at' => now()->subDay(),
        ]);

        $this->artisan('cutover:pos-check', ['--minutos' => 60])->assertSuccessful();
    }

    /**
     * O comando NÃO escreve nada.
     *
     * Roda com o sistema no ar: um diagnóstico que altera o que diagnostica é
     * pior que diagnóstico nenhum.
     */
    public function test_o_pos_check_nao_altera_nada(): void
    {
        Empresa::factory()->create();
        $this->cargaConcluida();

        $antes = [
            'empresas' => DB::table('empresas')->count(),
            'conversao_execucoes' => DB::table('conversao_execucoes')->count(),
            'conversao_quarentena' => DB::table('conversao_quarentena')->count(),
            'jobs' => DB::table('jobs')->count(),
        ];

        $this->artisan('cutover:pos-check')->assertSuccessful();

        foreach ($antes as $tabela => $total) {
            $this->assertSame($total, DB::table($tabela)->count(), "{$tabela} foi alterada pelo pós-check");
        }
    }
}
