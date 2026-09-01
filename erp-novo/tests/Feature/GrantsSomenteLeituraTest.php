<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `GRANT SELECT` não restringe — quem restringe é `REVOKE`.
 *
 * ## Como o defeito apareceu
 *
 * Escrevi `GRANT SELECT ON plano_conta_modelos TO erp_app` com a intenção de
 * deixá-la só de leitura. Conferindo em **homologação**, ela estava com
 * `INSERT, UPDATE, DELETE` também — e as três tabelas de conversão junto.
 *
 * A causa é uma decisão anterior, correta para o caso geral:
 * `grants_runtime_role` faz `ALTER DEFAULT PRIVILEGES ... GRANT SELECT, INSERT,
 * UPDATE, DELETE ON TABLES`. **Toda tabela nova nasce com escrita**, e o meu
 * `GRANT SELECT` apenas reafirmou o que já existia.
 *
 * É um defeito que só a conferência no banco real revelaria: o código dizia uma
 * coisa e o banco fazia outra, e nenhum teste comparava os dois.
 *
 * ## Por que importa
 *
 * `plano_conta_modelos` é o catálogo da PLATAFORMA: uma revenda que escrevesse
 * ali mudaria o ponto de partida de todas as outras. A RLS não a protege — é
 * tabela de plataforma, sem policy por tenant, justamente por isso.
 *
 * ## Por que as `conversao_*` continuam graváveis
 *
 * Eu ia revogá-las também, com a justificativa "quem escreve é o console, como
 * owner". Fui conferir e é **falso**: `RegistroDaConversao` usa `DB::table(...)`,
 * ou seja a conexão default — `erp_app`. Só as *migrations* rodam como
 * `pgsql_owner`.
 *
 * Revogar teria quebrado o registro da conversão da pior forma possível: toda
 * escrita do registro é protegida por `catch` (instrumentação não pode derrubar
 * a carga que observa), então a conversão rodaria inteira **sem deixar registro
 * nenhum**, e o bundle de evidência sairia vazio como se nada tivesse
 * acontecido.
 *
 * Este teste fixa isso: se alguém revogar a escrita delas sem antes mudar o
 * registro para a conexão de owner, ele reprova.
 */
class GrantsSomenteLeituraTest extends TestCase
{
    // Sem `RefreshDatabase`, e sem transacao: este teste so LE
    // `information_schema` — o catalogo que as migrations deixaram.
    //
    // `RefreshDatabase` tentaria `drop table` como `erp_app`, que NAO e dona das
    // tabelas (o dono e `pgsql_owner`), e o teste morria com
    // `must be owner of table agencias` antes de chegar a asserção. Foi o que
    // derrubou o gate do CI na primeira vez — e o defeito era do teste, nao do
    // que ele mede.
    //
    // `RlsCoberturaTest` ja resolvia isso com `DatabaseTransactions`; aqui nem
    // isso e preciso, porque nada e escrito.

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function tabelasComGrantEsperado(): array
    {
        return [
            'catálogo-modelo da plataforma' => ['plano_conta_modelos', ['SELECT']],

            // Os casos do meio: escrevem, não apagam.
            'consumo de integração' => ['integracao_consumos', ['SELECT', 'INSERT', 'UPDATE']],
            'uso das pontes legadas' => ['ponte_usos', ['SELECT', 'INSERT', 'UPDATE']],
            'retrato da fonte legada' => ['conversao_snapshots', ['SELECT', 'INSERT', 'UPDATE']],

            // As `conversao_*` mantêm escrita DE PROPÓSITO. Ver a nota abaixo:
            // quem escreve nelas é `RegistroDaConversao`, pela conexão default
            // (`erp_app`), e revogar quebraria o registro em silêncio.
            'registro de execução da conversão' => ['conversao_execucoes', ['SELECT', 'INSERT', 'UPDATE', 'DELETE']],
        ];
    }

    /**
     * @dataProvider tabelasComGrantEsperado
     *
     * @param  list<string>  $esperado
     */
    public function test_o_runtime_tem_exatamente_os_grants_declarados(string $tabela, array $esperado): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('grants são do PostgreSQL; o CI roda este teste com a role real');
        }

        $concedidos = DB::table('information_schema.role_table_grants')
            ->where('grantee', 'erp_app')
            ->where('table_name', $tabela)
            ->pluck('privilege_type')
            ->map(fn ($p) => strtoupper((string) $p))
            ->unique()
            ->sort()
            ->values()
            ->all();

        sort($esperado);

        $this->assertSame(
            $esperado,
            $concedidos,
            "{$tabela}: o banco não bate com a intenção da migration. ".
            'Lembre que `ALTER DEFAULT PRIVILEGES` dá escrita a toda tabela nova — '.
            'só `REVOKE` restringe.',
        );
    }
}
