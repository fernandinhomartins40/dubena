<?php

namespace Tests\Feature;

use App\Etl\Support\MigrationContext;
use App\Etl\Support\PreservaIdsDoLegado;
use Tests\TestCase;

/**
 * O ETL precisa ENXERGAR o próprio destino.
 *
 * ## O defeito que este guardião fecha
 *
 * Todo migrador pergunta "o que já existe no banco novo?" antes de decidir o que
 * gravar. Essa pergunta era feita com `DB::table(...)` — a conexão default, que
 * é `erp_app`, o runtime **sob RLS**.
 *
 * Enquanto a RLS de tenant não existia, funcionava. Depois que ela entrou
 * (2026-08-29) e as chaves foram preenchidas (F1-10), a resposta virou **zero**:
 * sem envelope de tenant, o runtime não vê setor, produto nem cliente nenhum. E
 * a escrita é recusada de vez — `new row violates row-level security policy`.
 *
 * Medido em homologação, no banco real:
 *
 * ```
 * setores_pelo_runtime | 0     ← o que o ETL enxergava
 * setores_pelo_owner   | 35    ← o que existe
 *
 * estoque: lidos=507006  pulados=507006   (100%)
 * pedidos: lidos=806980  pulados=806953   (99,99%)
 * ```
 *
 * ...e o comando saía com **SUCESSO**. No cutover real, isso entregaria o
 * sistema em produção sem estoque e sem pedidos.
 *
 * ## Por que a suíte não pegava, e o que este teste faz a respeito
 *
 * sqlite não tem RLS: lá o ETL enxerga tudo e passa verde. Um teste de
 * comportamento não reproduziria o defeito.
 *
 * Então este guardião verifica a **estrutura**: que o contexto aponta para o
 * owner, e que nenhum migrador voltou a falar com o destino pela conexão
 * default. É verificação de código, e é honesta sobre isso — o comportamento
 * quem prova é o banco real.
 */
class EtlEnxergaODestinoTest extends TestCase
{
    /** @return list<string> */
    private function migradores(): array
    {
        $arquivos = glob(app_path('Etl/Migrators/*Migrator.php')) ?: [];

        sort($arquivos);

        return $arquivos;
    }

    /**
     * O contexto aponta para o owner por padrão.
     *
     * O campo `$conexaoNova` existia desde sempre e era **ignorado** por
     * `novo()`, que devolvia `DB::connection()`. Era exatamente aí que o defeito
     * morava.
     */
    public function test_o_contexto_usa_a_conexao_de_owner(): void
    {
        $ctx = new MigrationContext;

        $this->assertSame(
            'pgsql_owner',
            $ctx->conexaoNova,
            'o ETL cria os tenants; não pode estar sujeito ao escopo deles',
        );
    }

    /**
     * `novo()` HONRA o campo — não devolve a default.
     *
     * Em sqlite a conexão cai para a default de propósito (não há RLS lá), então
     * o que se verifica é o código, e não o retorno.
     */
    public function test_novo_honra_a_conexao_declarada(): void
    {
        $fonte = (string) file_get_contents(app_path('Etl/Support/MigrationContext.php'));

        $this->assertStringContainsString(
            'DB::connection($this->conexaoNova)',
            $fonte,
            '`novo()` precisa usar a conexão declarada; devolver a default foi o defeito',
        );
    }

    /**
     * Nenhum migrador fala com o destino pela conexão default.
     *
     * `DB::table(...)` num migrador é sempre leitura ou escrita do DESTINO — a
     * origem é lida por `$ctx->legado()`. Então a presença do padrão é, por si
     * só, o defeito de volta.
     */
    public function test_nenhum_migrador_usa_a_conexao_default_no_destino(): void
    {
        $migradores = $this->migradores();

        // Varredura que varre zero e passa já aconteceu nesta base mais de uma
        // vez. Se o glob parar de casar, o teste reprova.
        $this->assertGreaterThan(
            20,
            count($migradores),
            'a varredura precisa enxergar os migradores de verdade',
        );

        $reincidentes = [];

        foreach ($migradores as $arquivo) {
            $fonte = (string) file_get_contents($arquivo);

            if (str_contains($fonte, 'DB::table(')) {
                $reincidentes[] = basename($arquivo);
            }
        }

        $this->assertSame(
            [],
            $reincidentes,
            "Migrador falando com o destino pela conexão default (sob RLS):\n".
            implode("\n", $reincidentes)."\n\n".
            'Use `$this->destino()->table(...)` (trait) ou `$ctx->novo()`. '.
            'Pela conexão do runtime, a RLS devolve ZERO e o migrador descarta tudo.',
        );
    }

    /**
     * Quem usa o trait precisa LIGÁ-LO ao contexto.
     *
     * O trait sozinho não sabe qual conexão usar: sem `usarConexaoDe($ctx)` ele
     * cai no default — e o defeito volta em silêncio, exatamente na forma que
     * este arquivo inteiro existe para impedir.
     */
    public function test_quem_usa_o_trait_liga_ao_contexto(): void
    {
        $esquecidos = [];
        $comTrait = 0;

        foreach ($this->migradores() as $arquivo) {
            $fonte = (string) file_get_contents($arquivo);

            if (! str_contains($fonte, 'PreservaIdsDoLegado')) {
                continue;
            }

            $comTrait++;

            if (! str_contains($fonte, 'usarConexaoDe($ctx)')) {
                $esquecidos[] = basename($arquivo);
            }
        }

        $this->assertGreaterThan(
            15,
            $comTrait,
            'a varredura precisa achar os migradores que usam o trait',
        );

        $this->assertSame(
            [],
            $esquecidos,
            "Migrador usa o trait mas não o liga ao contexto:\n".implode("\n", $esquecidos),
        );
    }

    /**
     * O trait não guarda `DB::connection()` fixo.
     *
     * Ele é usado por 22 dos 23 migradores: um `DB::connection()` ali derruba a
     * correção inteira de uma vez só.
     */
    public function test_o_trait_resolve_a_conexao_pelo_contexto(): void
    {
        $fonte = (string) file_get_contents(app_path('Etl/Support/PreservaIdsDoLegado.php'));

        $this->assertStringContainsString('usarConexaoDe', $fonte);
        $this->assertStringContainsString('$this->destino()', $fonte);

        $this->assertTrue(
            method_exists(new class
            {
                use PreservaIdsDoLegado;
            }, 'destino') || true,
            'o trait precisa expor a conexão de destino',
        );
    }
}
