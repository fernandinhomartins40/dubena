<?php

namespace Tests\Feature;

use App\Domain\Legado\UsoDaPonte;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * F9-07 — medir o uso das pontes legadas.
 *
 * A tarefa pede "medir uso, substituir por contratos canônicos e remover
 * gradualmente". Estes testes fixam a primeira parte, que é a que autoriza as
 * outras duas: sem número, remover endpoint é apostar — o MovelApp está em
 * `targetSdk 28` e não publica na Play Store, então um endpoint removido cedo
 * demais aparece como venda travada em campo.
 */
class UsoDaPonteTest extends TestCase
{
    use RefreshDatabase;

    private function uso(): UsoDaPonte
    {
        return app(UsoDaPonte::class);
    }

    public function test_conta_a_chamada_por_ponte_endpoint_e_dia(): void
    {
        $this->uso()->registrar('movelapp', 'getPedidosPendentes', empresaId: null);
        $this->uso()->registrar('movelapp', 'getPedidosPendentes', empresaId: null);
        $this->uso()->registrar('nfweb', 'savePedido', empresaId: null);

        $this->assertSame(2, (int) DB::table('ponte_usos')
            ->where('endpoint', 'getPedidosPendentes')->value('chamadas'));

        $this->assertSame(2, DB::table('ponte_usos')->count(), 'um agregado por (ponte, endpoint, dia)');
    }

    /**
     * O caso que quebraria o agregado sem ninguém notar.
     *
     * `login` e `init` acontecem ANTES de haver tenant resolvido, então
     * `empresa_id` nulo é o caso NORMAL aqui — e é o mais frágil: em SQL cru,
     * `empresa_id = NULL` nunca casa, e o registrador inseriria uma linha nova a
     * cada chamada.
     *
     * O query builder do Laravel converte `where(col, null)` em `IS NULL`
     * sozinho, então o `whereNull` do código não é o que salva hoje. Este teste
     * é que é: ele reprova qualquer reescrita — `updateOrInsert` com chave em
     * array, SQL à mão — que perca a comparação com nulo.
     *
     * O sintoma seria cruel: a soma total continuaria certa, e só o "quantas
     * revendas" e o "uma linha por dia" estariam destruídos.
     */
    public function test_empresa_nula_agrega_numa_linha_so(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->uso()->registrar('nfweb', 'login', empresaId: null);
        }

        $this->assertSame(1, DB::table('ponte_usos')->count(), 'empresa nula tem de casar com whereNull');
        $this->assertSame(5, (int) DB::table('ponte_usos')->value('chamadas'));
    }

    /** Revendas diferentes são linhas diferentes: é o "quem ainda chama". */
    public function test_separa_por_revenda(): void
    {
        $a = Empresa::factory()->create();
        $b = Empresa::factory()->create();

        $this->uso()->registrar('movelapp', 'getVeiculos', empresaId: $a->id);
        $this->uso()->registrar('movelapp', 'getVeiculos', empresaId: $b->id);

        $this->assertSame(2, DB::table('ponte_usos')->where('endpoint', 'getVeiculos')->count());
    }

    /** O tenant vem da empresa — coluna criada e deixada nula não responde nada. */
    public function test_grava_o_tenant_da_empresa(): void
    {
        $empresa = Empresa::factory()->create();

        $this->uso()->registrar('nfweb', 'getCliente', empresaId: $empresa->id);

        $this->assertSame(
            (int) $empresa->tenant_account_id,
            (int) DB::table('ponte_usos')->value('tenant_account_id'),
            'sem o tenant preenchido a policy canônica não tem o primeiro argumento',
        );
    }

    /**
     * Recusa conta separado.
     *
     * Endpoint muito chamado e sempre recusado não é uso — é app velho
     * insistindo. Somá-lo às chamadas boas faria a leitura de "está em uso" ser
     * falsa, e a ponte nunca sairia.
     */
    public function test_recusa_conta_separado(): void
    {
        $this->uso()->registrar('nfweb', 'savePedido', empresaId: null, recusada: true);
        $this->uso()->registrar('nfweb', 'savePedido', empresaId: null, recusada: false);

        $linha = DB::table('ponte_usos')->first();

        $this->assertSame(2, (int) $linha->chamadas);
        $this->assertSame(1, (int) $linha->recusas);
    }

    /**
     * Versão comparada como VERSÃO, não como string.
     *
     * `'1.10' > '1.9'` é falso em comparação de string. Guardar `1.9` como "a
     * mais nova" inverteria a decisão: pareceria que só APK velho chama o
     * endpoint, quando o novo também chama — e a ponte sairia embaixo de quem
     * atualizou.
     */
    public function test_guarda_a_versao_mais_nova_e_nao_a_maior_string(): void
    {
        $this->uso()->registrar('movelapp', 'getEmpresas', empresaId: null, versaoApp: '1.9');
        $this->uso()->registrar('movelapp', 'getEmpresas', empresaId: null, versaoApp: '1.10');

        $this->assertSame('1.10', DB::table('ponte_usos')->value('ultima_versao_app'));
    }

    /** App antigo não manda versão; nulo não pode apagar a versão já vista. */
    public function test_chamada_sem_versao_nao_apaga_a_versao_conhecida(): void
    {
        $this->uso()->registrar('movelapp', 'getEmpresas', empresaId: null, versaoApp: '2.0');
        $this->uso()->registrar('movelapp', 'getEmpresas', empresaId: null, versaoApp: null);

        $this->assertSame('2.0', DB::table('ponte_usos')->value('ultima_versao_app'));
    }

    /**
     * A medição não pode derrubar o que mede.
     *
     * Uma falha ao contar viraria erro numa venda em campo. O preço de perder
     * uma linha de estatística é incomparavelmente menor.
     */
    public function test_falha_ao_medir_nao_propaga(): void
    {
        DB::statement('DROP TABLE ponte_usos');

        $this->uso()->registrar('movelapp', 'getVeiculos', empresaId: null);

        $this->assertTrue(true, 'registrar não pode lançar mesmo sem a tabela');
    }

    /**
     * O middleware é o ponto de captura — não cada controller.
     *
     * Instrumentar controller a controller depende de alguém lembrar, e
     * instrumentação incompleta é pior que nenhuma: ela autoriza remover
     * justamente o que não foi visto.
     */
    public function test_o_middleware_conta_a_chamada_de_verdade(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        Route::post('_teste/ponte/getVeiculos', fn () => response()->json(['data' => []]))
            ->middleware(['auth:sanctum', 'dialeto.legado:dados']);

        $this->actingAs($user, 'sanctum')
            ->postJson('_teste/ponte/getVeiculos')
            ->assertOk()
            // A tradução de dialeto continua valendo: quem mede não pode mudar
            // o que o app recebe.
            ->assertJsonPath('status', 'OK');

        $linha = DB::table('ponte_usos')->first();

        $this->assertNotNull($linha, 'o middleware precisa contar; sem isso a medição fica incompleta');
        $this->assertSame('movelapp', $linha->ponte, 'a chave `dados` identifica o MovelApp');
        $this->assertSame('getVeiculos', $linha->endpoint);
        $this->assertSame($empresa->id, (int) $linha->empresa_id);
    }

    /** 422 é recusa de REGRA — e o middleware precisa contá-la como tal. */
    public function test_o_middleware_conta_a_recusa_como_recusa(): void
    {
        $user = User::factory()->create();

        Route::post('_teste/ponte/savePedido', fn () => response()->json(['message' => 'sem limite'], 422))
            ->middleware(['auth:sanctum', 'dialeto.legado:data']);

        $this->actingAs($user, 'sanctum')
            ->postJson('_teste/ponte/savePedido')
            ->assertOk()
            ->assertJsonPath('status', 'OPS');

        $linha = DB::table('ponte_usos')->first();

        $this->assertSame(1, (int) $linha->recusas);
        $this->assertSame('nfweb', $linha->ponte);
    }

    /**
     * `login` não pode aparecer como "5000 chamadas, 0 revendas".
     *
     * `count(distinct)` IGNORA nulos em SQL, e nulo é o caso normal aqui —
     * `login` e `init` acontecem antes de haver tenant resolvido. Sem a contagem
     * separada, o endpoint **mais vivo** da ponte apareceria como o mais morto,
     * que é o pior erro de leitura possível neste relatório.
     */
    public function test_chamada_sem_tenant_nao_some_da_contagem(): void
    {
        $empresa = Empresa::factory()->create();

        $this->uso()->registrar('nfweb', 'login', empresaId: null);
        $this->uso()->registrar('nfweb', 'login', empresaId: null);
        $this->uso()->registrar('nfweb', 'getCliente', empresaId: $empresa->id);

        $resumo = collect($this->uso()->resumo('2000-01-01', '2099-12-31'))->keyBy('endpoint');

        $login = $resumo['login'];

        $this->assertSame(2, (int) $login['chamadas']);
        $this->assertSame(0, (int) $login['revendas_identificadas'], 'nenhuma revenda identificada — é verdade');
        $this->assertSame(
            2,
            (int) $login['chamadas_sem_tenant'],
            'e as chamadas existem; sem esta coluna o endpoint pareceria morto',
        );

        $this->assertSame(1, (int) $resumo['getCliente']['revendas_identificadas']);
    }

    /**
     * O relatório precisa mostrar o ZERO.
     *
     * Este é o ponto todo da tarefa. Um relatório que lista só o que foi chamado
     * responde "o que está em uso"; a pergunta é a oposta — **o que já pode
     * sair**. Partindo da tabela de medição, o endpoint nunca chamado
     * simplesmente não apareceria, e o silêncio se confundiria com ausência de
     * dado.
     *
     * É a mesma armadilha que esta base já pagou duas vezes (registry vazio
     * imprimindo "concluído", teste que varria zero arquivos).
     */
    public function test_o_relatorio_lista_endpoint_com_zero_chamadas(): void
    {
        $this->uso()->registrar('movelapp', 'getVeiculos', empresaId: null);

        $this->artisan('ponte:uso', ['--dias' => 30])
            ->expectsOutputToContain('getVeiculos')
            // Nunca chamado, e mesmo assim na tabela — é o que autoriza propor
            // a remoção.
            ->expectsOutputToContain('setAndroidMensagem')
            ->assertSuccessful();
    }

    /**
     * Não conseguir LER não pode virar "ninguém usa".
     *
     * `resumo()` devolvia lista vazia no `catch`, e o relatório traduz lista
     * vazia em "zero chamadas em todos os endpoints" — que é exatamente a saída
     * que AUTORIZA remover as 29 rotas de ponte. Uma falha de leitura viraria
     * permissão para desligar os apps em campo.
     *
     * Repare a assimetria proposital com `registrar()`, que engole tudo:
     * perder uma linha de estatística é barato, afirmar ausência de uso não é.
     */
    public function test_falha_de_leitura_reprova_em_vez_de_dizer_que_ninguem_usa(): void
    {
        DB::statement('DROP TABLE ponte_usos');

        $this->artisan('ponte:uso')
            ->doesntExpectOutputToContain('sem nenhuma chamada no recorte')
            ->assertFailed();
    }

    /**
     * O relatório varre alguma coisa de verdade.
     *
     * Guardião que varre zero e passa já aconteceu aqui. Se o comando parar de
     * enxergar o router, ele tem de REPROVAR, não imprimir uma tabela vazia
     * dizendo que ninguém usa nada — essa saída autorizaria remover as 29 rotas.
     */
    public function test_o_relatorio_reprova_se_nao_enxergar_rota_nenhuma(): void
    {
        $this->artisan('ponte:uso', ['--ponte' => 'nao-existe'])
            ->assertFailed();
    }
}
