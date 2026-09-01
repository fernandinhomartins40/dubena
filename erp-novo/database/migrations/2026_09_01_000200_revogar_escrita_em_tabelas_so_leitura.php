<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `GRANT SELECT` não restringe — quem restringe é `REVOKE`.
 *
 * ## O que a conferência na VPS mostrou
 *
 * Escrevi `GRANT SELECT ON plano_conta_modelos TO erp_app` e
 * `GRANT SELECT ON conversao_* TO erp_app` com a intenção de deixá-las **só de
 * leitura** para o runtime. Fui olhar em homologação e as quatro estavam com
 * `INSERT, UPDATE, DELETE` também.
 *
 * A causa é uma decisão anterior, e correta para o caso geral:
 * `2026_08_14_000200_grants_runtime_role` faz
 *
 * ```sql
 * ALTER DEFAULT PRIVILEGES IN SCHEMA public
 *   GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO erp_app;
 * ```
 *
 * Ou seja: **toda tabela nova nasce com escrita**. Meu `GRANT SELECT` não tirou
 * nada — apenas reafirmou uma permissão que já existia.
 *
 * ## O que esta migration acabou revogando, e o que NÃO
 *
 * Só `plano_conta_modelos`. É o catálogo-modelo da PLATAFORMA: uma revenda que
 * editasse ali mudaria o ponto de partida de todas as outras — ela edita a
 * **cópia** dela, em `planos_conta`. O risco não é hipotético; é escrita por
 * engano num `updateOrCreate` mal escopado, e a RLS não protege (tabela de
 * plataforma não tem policy por tenant, justamente por ser de plataforma).
 *
 * As três `conversao_*` estavam nesta lista e **saíram**. Ver a constante
 * `SOMENTE_LEITURA` para o motivo — em resumo: quem escreve nelas é
 * `RegistroDaConversao`, pela conexão default (`erp_app`), não como owner.
 * Revogar quebraria o registro da conversão em silêncio.
 *
 * ## Idempotente, e sem `ALTER DEFAULT PRIVILEGES` global
 *
 * Mexer no default do schema mudaria o comportamento de **toda** tabela futura,
 * inclusive as de negócio que precisam de escrita. O ajuste é nominal, por
 * tabela, e reexecutar não quebra: `REVOKE` do que já foi revogado é no-op.
 */
return new class extends Migration
{
    /**
     * Tabelas em que o runtime lê e não escreve, com o motivo.
     *
     * ## As `conversao_*` NÃO estão aqui, e quase estiveram
     *
     * Eu tinha escrito nesta lista as três tabelas da conversão, com a
     * justificativa "quem escreve é o console, como owner". **Fui conferir e é
     * falso:** `RegistroDaConversao` usa `DB::table(...)`, ou seja a conexão
     * default — que é `erp_app`, não `pgsql_owner`. Só as *migrations* rodam
     * como owner.
     *
     * Revogar a escrita ali quebraria o registro da conversão em produção, e da
     * pior forma possível: toda escrita de `RegistroDaConversao` é protegida por
     * `catch` (de propósito — instrumentação não pode derrubar a carga que
     * observa). A conversão rodaria inteira **sem deixar registro nenhum**, e o
     * bundle de evidência sairia vazio como se nada tivesse acontecido.
     *
     * Nenhum teste local pegaria: sqlite não tem grants.
     *
     * A proteção dessas três tabelas, se for desejada, passa por fazer o
     * registro escrever pela conexão `pgsql_owner` primeiro. É mudança de
     * comportamento do ETL, não de permissão — e não cabe nesta migration.
     */
    private const SOMENTE_LEITURA = [
        'plano_conta_modelos' => 'catálogo-modelo da plataforma; o runtime só lê (a escrita vai para `planos_conta`, a cópia do grupo)',
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_keys(self::SOMENTE_LEITURA) as $tabela) {
            // `to_regclass` em vez de `hasTable`: a migration precisa ser
            // reexecutável num banco onde alguma dessas tabelas ainda não
            // nasceu (rollback parcial), sem explodir.
            $existe = DB::selectOne('SELECT to_regclass(?) AS r', ['public.'.$tabela])?->r;

            if ($existe === null) {
                continue;
            }

            DB::statement("REVOKE INSERT, UPDATE, DELETE ON {$tabela} FROM erp_app");
            DB::statement("GRANT SELECT ON {$tabela} TO erp_app");
        }

        // `integracao_consumos` é o caso do meio: o runtime PRECISA escrever
        // (é ele quem conta as chamadas) e não tem por que apagar. Consumo
        // apagado é fatura sem origem — e a linha antiga é justamente a prova
        // de quanto se gastou.
        $consumo = DB::selectOne('SELECT to_regclass(?) AS r', ['public.integracao_consumos'])?->r;

        if ($consumo !== null) {
            DB::statement('REVOKE DELETE ON integracao_consumos FROM erp_app');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Devolve o que o default do schema daria — é o estado anterior exato,
        // e não uma permissão inventada.
        foreach (array_keys(self::SOMENTE_LEITURA) as $tabela) {
            $existe = DB::selectOne('SELECT to_regclass(?) AS r', ['public.'.$tabela])?->r;

            if ($existe !== null) {
                DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON {$tabela} TO erp_app");
            }
        }

        $consumo = DB::selectOne('SELECT to_regclass(?) AS r', ['public.integracao_consumos'])?->r;

        if ($consumo !== null) {
            DB::statement('GRANT DELETE ON integracao_consumos TO erp_app');
        }
    }
};
