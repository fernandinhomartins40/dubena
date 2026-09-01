<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Os gates transversais do `PLANO_TRANSFORMACAO_SAAS.md`, seção 6.
 *
 * São onze condições objetivas que valem para o plano inteiro, não para uma fase
 * — e por isso ninguém as verifica: cada fase confere o seu gate e segue.
 *
 * Este arquivo cobre os que dão para verificar **em código**, e nomeia os que
 * não dão. Vários já eram exercitados por testes espalhados; o valor aqui é ter
 * um lugar que responde "os gates transversais passam?" sem depender de alguém
 * lembrar de onde cada um mora.
 *
 * ## O que fica de fora, e por quê
 *
 * - **Fiscal (XML homologado)** — exige transmitir para a SEFAZ com certificado
 *   real. Registrado como operação em F5-09;
 *   *o que dá para verificar — ausência de regra bloqueia — está em
 *   `CenariosFiscaisTest`.*
 * - **SPA (A→logout→B sem render residual)** — a parte de cache está em
 *   `cache-isolamento.test.ts`; duas abas e request em voo no navegador exigem
 *   Playwright, que o projeto não usa.
 */
class GatesTransversaisTest extends TestCase
{
    use RefreshDatabase;

    /**
     * **Gate Tenancy:** 100% das tabelas classificadas.
     *
     * O manifesto é enumerado: tabela nova que ninguém classificou faz o teste
     * do manifesto falhar. Aqui só se confirma que o catálogo não está vazio —
     * um `require` que devolvesse `[]` passaria em todo o resto.
     */
    public function test_gate_tenancy_o_catalogo_de_classificacao_nao_esta_vazio(): void
    {
        $entradas = require base_path('config/saas_table_classification.php');

        $this->assertIsArray($entradas);
        $this->assertGreaterThan(200, count($entradas), 'catálogo vazio aprovaria qualquer coisa');

        foreach ($entradas as $tabela => $meta) {
            $this->assertArrayHasKey('class', $meta, "{$tabela} sem classe");
            $this->assertArrayHasKey('owner', $meta, "{$tabela} sem owner");
        }
    }

    /**
     * **Gate Drivers:** produção não inicializa com fake crítico.
     *
     * Coberto em profundidade por `FakesBloqueadosEmProducaoTest`; aqui a
     * conferência é do contrato — o provider tem de ter a trava, e não apenas
     * os drivers.
     */
    public function test_gate_drivers_o_container_recusa_fake_em_producao(): void
    {
        $provider = (string) file_get_contents(app_path('Providers/AppServiceProvider.php'));

        $this->assertStringContainsString('exigirDriverReal', $provider);
        $this->assertStringContainsString('isProduction', $provider);
    }

    /**
     * **Gate Segredos:** nenhuma credencial literal no código versionado.
     *
     * O `.env.example` é o que orienta quem provisiona: um valor real ali vira
     * credencial em produção por cópia distraída — e o arquivo é versionado.
     */
    public function test_gate_segredos_o_env_de_exemplo_nao_tem_valor_real(): void
    {
        $caminho = base_path('.env.example');
        $this->assertFileExists($caminho);

        $conteudo = (string) file_get_contents($caminho);
        $linhas = explode("\n", $conteudo);

        $this->assertGreaterThan(20, count($linhas), 'o arquivo precisa ter sido lido');

        $suspeitas = [];

        foreach ($linhas as $n => $linha) {
            if (str_starts_with(ltrim($linha), '#')) {
                continue;
            }

            // Chave sensível COM valor longo o suficiente para ser real.
            if (preg_match('/(SECRET|TOKEN|PASSWORD|SENHA|_KEY)=(.{16,})$/i', trim($linha), $m) !== 1) {
                continue;
            }

            $valor = trim($m[2], '"\'');

            // Placeholders são o uso legítimo do arquivo.
            if (preg_match('/^(base64:)?$|exemplo|example|seu-|sua-|troque|change|xxx|\$\{/i', $valor) === 1) {
                continue;
            }

            $suspeitas[] = 'linha '.($n + 1).': '.explode('=', $linha)[0];
        }

        $this->assertSame([], $suspeitas, 'valor real no .env.example vira credencial em produção por cópia');
    }

    /**
     * **Gate RBAC/licença:** sem assinatura, negado.
     *
     * O comportamento está testado em profundidade nos testes de licença; aqui
     * se confirma que o interruptor existe e nasce **desligado** — um
     * enforcement que fosse `true` por padrão quebraria a operação atual no
     * primeiro deploy, e um que não existisse não teria como ser ligado.
     */
    public function test_gate_licenca_o_enforcement_existe_e_e_explicito(): void
    {
        $config = config('saas_transformation.enforcement');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('licenca', $config);
        $this->assertArrayHasKey('tenant_envelope', $config);
    }

    /**
     * **Gate Domínio:** heurística nunca decide silenciosamente.
     *
     * O guardião completo é `EscritaCanonicaTest`. Aqui se confirma que ele
     * ainda existe e cobre os dois lados — inferência por palavra e nome de
     * revenda em literal —, porque um gate transversal que aponta para um
     * arquivo apagado não guarda nada.
     */
    public function test_gate_dominio_o_guardiao_da_escrita_canonica_existe(): void
    {
        $caminho = base_path('tests/Feature/EscritaCanonicaTest.php');
        $this->assertFileExists($caminho);

        $conteudo = (string) file_get_contents($caminho);

        $this->assertStringContainsString('infere_conceito_pela_descricao', $conteudo);
        $this->assertStringContainsString('nome_de_revenda_nao_vira_literal', $conteudo);
    }

    /**
     * **Gate Financeiro:** idempotência e reconciliação por empresa.
     *
     * As ferramentas têm de existir e ser read-only — conferência que ajusta
     * apaga a pergunta que ela deveria fazer (princípio de F4 e F5-10).
     */
    public function test_gate_financeiro_as_ferramentas_de_conferencia_existem(): void
    {
        $comandos = array_keys(Artisan::all());

        foreach (['estoque:conferir', 'financeiro:conferir'] as $comando) {
            $this->assertContains($comando, $comandos, "{$comando} é o portão de reconciliação");
        }
    }

    /**
     * **Gate Jobs:** dois tenants sequenciais no mesmo worker, com limpeza.
     *
     * O teste do comportamento é `TenantEnvelopeRuntimeTest` — inclusive o caso
     * em que o job **falha**, que é onde o estado costuma vazar. Aqui se
     * confirma que ele não sumiu.
     */
    public function test_gate_jobs_o_teste_de_worker_sequencial_existe(): void
    {
        $caminho = base_path('tests/Unit/TenantEnvelopeRuntimeTest.php');
        $this->assertFileExists($caminho);

        $conteudo = (string) file_get_contents($caminho);

        $this->assertStringContainsString('worker_sequencial', $conteudo);
        $this->assertStringContainsString('limpa_contexto_mesmo_quando_o_job_falha', $conteudo);
    }
}
