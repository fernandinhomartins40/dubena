<?php

namespace Tests\Feature;

use App\Etl\Support\RegistroDaConversao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F7-13 — o bundle imutável de evidência.
 *
 * ## Por que arquivo, e não tela
 *
 * A palavra que decide o formato é **imutável**. Uma tela consulta o banco e
 * mostra o estado de *agora*; a evidência precisa mostrar o estado *daquele
 * momento*, meses depois, quando alguém perguntar por que a conversão foi
 * aprovada.
 *
 * O hash é o que torna a imutabilidade **verificável** — sem ele, "imutável" é
 * promessa.
 *
 * ## O que o bundle NÃO inventa
 *
 * Aprovações e resultado do rollback ensaiado são **atos humanos**: alguém
 * assina, alguém cronometra. Gerá-los a partir do banco seria inventar
 * assinatura — o oposto do que uma evidência serve para fazer.
 *
 * Ficam declarados e **vazios**, para quem preencher saber que faltam. Relatório
 * que omite o que não tem é pior que um que mostra a lacuna.
 */
class EvidenciaDaConversaoTest extends TestCase
{
    use RefreshDatabase;

    private function caminho(): string
    {
        return storage_path('app/testes-evidencia.json');
    }

    protected function tearDown(): void
    {
        @unlink($this->caminho());
        parent::tearDown();
    }

    /** @return array<string,mixed> */
    private function gerar(bool $esperaFalha = false): array
    {
        // Opcao como ARRAY, e nao concatenada na string do comando: o caminho
        // no Windows tem `\`, e o parser da linha de comando o consome como
        // escape — o comando recebia um caminho truncado e falhava.
        $this->artisan('conversao:evidencia', ['--saida' => $this->caminho()])
            ->{$esperaFalha ? 'assertFailed' : 'assertSuccessful'}();

        return json_decode((string) file_get_contents($this->caminho()), true);
    }

    public function test_o_bundle_reune_o_que_o_sistema_registrou(): void
    {
        $registro = app(RegistroDaConversao::class);
        $registro->iniciar('clientes', dryRun: false, comInvariantes: true);
        $registro->linhagem('oracle', 'clientes', '4218', 'clientes', 991, 'v2');
        $registro->encerrar('CONCLUIDA', 'ok', ['lidas' => 10, 'gravadas' => 10]);

        $bundle = $this->gerar();

        $this->assertCount(1, $bundle['execucoes']);
        $this->assertSame('CONCLUIDA', $bundle['execucoes'][0]['situacao']);

        $this->assertSame('oracle', $bundle['linhagem'][0]['sistema_origem']);
        $this->assertSame(1, (int) $bundle['linhagem'][0]['linhas']);
    }

    /**
     * O hash cobre o conteúdo, e é recalculável.
     *
     * Sem poder recalcular, o hash não prova nada — seria só um campo a mais.
     */
    public function test_o_hash_e_conferivel(): void
    {
        $bundle = $this->gerar();

        $this->assertArrayHasKey('hash_sha256', $bundle);
        $this->assertSame(64, strlen($bundle['hash_sha256']));

        // Recalcula do mesmo jeito que o comando: sem o próprio hash.
        $semHash = $bundle;
        unset($semHash['hash_sha256']);

        $this->assertSame(
            hash('sha256', (string) json_encode($semHash, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            $bundle['hash_sha256'],
            'o hash tem de ser recalculável — senão não prova imutabilidade',
        );
    }

    /**
     * Aprovações e rollback ficam DECLARADOS e vazios.
     *
     * Omiti-los deixaria o bundle parecendo completo; inventá-los seria pior
     * ainda. A lacuna visível é o comportamento certo.
     */
    public function test_atos_humanos_ficam_declarados_e_vazios(): void
    {
        $bundle = $this->gerar();

        foreach (['tecnica', 'operacional', 'financeira_fiscal', 'controlador_dos_dados'] as $quem) {
            $this->assertArrayHasKey($quem, $bundle['aprovacoes'], "aprovação '{$quem}' precisa estar declarada");
            $this->assertNull($bundle['aprovacoes'][$quem], 'assinatura não se gera a partir do banco');
        }

        $this->assertArrayHasKey('rollback_ensaiado', $bundle);
        $this->assertNull($bundle['rollback_ensaiado']['executado_em']);
    }

    /**
     * Quarentena PENDENTE reprova o comando.
     *
     * O gate F8 exige zero quarentena bloqueante. O bundle documenta a
     * situação; sair com sucesso enquanto há caso não resolvido faria um script
     * de deploy tratar a evidência como aprovação.
     */
    public function test_quarentena_pendente_reprova(): void
    {
        $registro = app(RegistroDaConversao::class);
        $registro->iniciar(null, false, false);
        $registro->quarentena('oracle', 'clientes', '77', 'OWNER_AMBIGUO', 'aparece em duas empresas');

        $bundle = $this->gerar(esperaFalha: true);

        $this->assertSame(1, $bundle['quarentena']['pendentes']);
        $this->assertSame(1, $bundle['quarentena']['total']);
    }

    /**
     * Não conseguir LER a quarentena não é o mesmo que ela estar limpa.
     *
     * O `catch` devolvia `pendentes => 0`, e o comando saía com SUCESSO — um
     * script de deploy leria isso como aprovação para virar. Fail-closed em
     * evidência é o mesmo princípio que já vale para dinheiro e identidade
     * nesta base.
     */
    public function test_falha_ao_ler_a_quarentena_reprova_em_vez_de_dizer_zero(): void
    {
        DB::statement('DROP TABLE conversao_quarentena');

        $bundle = $this->gerar(esperaFalha: true);

        $this->assertNull(
            $bundle['quarentena']['pendentes'],
            '"não consegui olhar" precisa ser distinguível de "não há caso em aberto"',
        );
    }

    /** Resolvida deixa de bloquear — mas continua no bundle, como histórico. */
    public function test_quarentena_resolvida_nao_bloqueia_e_permanece_no_bundle(): void
    {
        $registro = app(RegistroDaConversao::class);
        $registro->iniciar(null, false, false);
        $registro->quarentena('oracle', 'clientes', '77', 'OWNER_AMBIGUO', 'aparece em duas empresas');

        DB::table('conversao_quarentena')->update(['decisao' => 'DESCARTADA']);

        $bundle = $this->gerar();

        $this->assertSame(0, $bundle['quarentena']['pendentes']);
        $this->assertSame(1, $bundle['quarentena']['total'], 'o caso resolvido continua sendo evidência');
    }

    /** A versão do código entra: evidência sem isso é retrato sem data. */
    public function test_o_bundle_registra_a_versao_do_codigo(): void
    {
        $bundle = $this->gerar();

        $this->assertArrayHasKey('commit', $bundle['versoes']);
        $this->assertGreaterThan(0, $bundle['versoes']['migrations_aplicadas']);
        $this->assertSame('testing', $bundle['versoes']['ambiente']);
    }

    /** Contagem por empresa: é o número que a operação confere primeiro. */
    public function test_o_bundle_traz_contagem_por_empresa(): void
    {
        $bundle = $this->gerar();

        foreach (['clientes', 'pedidos', 'produtos', 'financeiros', 'notas_fiscais'] as $tabela) {
            $this->assertArrayHasKey($tabela, $bundle['contagens']);
        }
    }
}
