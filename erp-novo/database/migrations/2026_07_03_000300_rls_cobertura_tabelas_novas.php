<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RLS — recobertura + policy CAST-SAFE de TODAS as tabelas tenant (Q-6/MT-3 da
 * auditoria). Corre por ÚLTIMO, então é a fonte-da-verdade final da RLS.
 *
 * Faz duas coisas de uma vez:
 *  1) COBERTURA: descobre e isola tabelas com empresa_id/grupo_id criadas DEPOIS
 *     da rls_tenant_completa (2026_06_26_000300) — as migrations intermediárias
 *     (a3/a4/p2/l0/…) criavam suas próprias policies e algumas tabelas ficavam de
 *     fora (o RlsCoberturaTest pegou 4 em Postgres: cliente_enderecos, login_logs,
 *     platform_audit_logs, produto_condicao_precos — invisível na suíte sqlite).
 *  2) POLICY CAST-SAFE: recria TODA policy tenant_isolation com
 *     `nullif(current_setting(...),'')::int`. Sem o nullif antes do ::int, uma GUC
 *     VAZIA ('') — que o fim-de-requisição (ResolveTenant::terminate) seta para
 *     "sem tenant" — estourava "invalid input syntax for integer" (o planner do
 *     Postgres avalia o ramo direito do OR mesmo com o guard). As migrations
 *     anteriores usavam o cast inseguro; aqui todas são normalizadas.
 *
 * Também retira as tabelas de auditoria da RLS (allowlist): recebem empresa_id
 * NULL por design. Idempotente. NO-OP fora do PostgreSQL.
 */
return new class extends Migration
{
    /** @var list<string> Tabelas que NÃO devem ser isoladas (espelha a 000300). */
    private array $allowlist = [
        'grupos', 'users', 'role_user', 'permission_role',
        'empresa_user', 'empresa_configs', 'roles',
        'audit_logs', 'login_logs', 'platform_audit_logs',
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Higieniza: se a 000300 (versão anterior) FORÇOU RLS nas tabelas de
        // auditoria, remove — elas agora estão na allowlist (recebem empresa_id
        // NULL por design; a RLS FORCE quebrava o INSERT da auditoria de Empresa).
        foreach (['audit_logs', 'login_logs', 'platform_audit_logs'] as $t) {
            if (Schema::hasTable($t)) {
                DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$t}");
                DB::statement("ALTER TABLE {$t} NO FORCE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$t} DISABLE ROW LEVEL SECURITY");
            }
        }

        $comEmpresa = $this->tabelas('empresa_id');
        foreach ($comEmpresa as $tabela) {
            $this->aplicarPolicy($tabela, 'empresa_id', 'app.empresa_id');
        }

        foreach ($this->tabelas('grupo_id') as $tabela) {
            if (in_array($tabela, $comEmpresa, true)) {
                continue; // a policy de empresa (mais restritiva) já cobre
            }
            $this->aplicarPolicy($tabela, 'grupo_id', 'app.grupo_id');
        }
    }

    public function down(): void
    {
        // Não remove policies: a cobertura de RLS não deve regredir num rollback
        // desta migration (as tabelas antigas continuam cobertas pela 000300).
    }

    /** @return list<string> */
    private function tabelas(string $coluna): array
    {
        $tabelas = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('column_name', $coluna)
            ->pluck('table_name')
            ->all();

        return array_values(array_filter(
            $tabelas,
            fn (string $t) => ! in_array($t, $this->allowlist, true) && Schema::hasTable($t),
        ));
    }

    /** Idêntica à 000300: ENABLE+FORCE + policy USING/WITH CHECK por variável de sessão. */
    private function aplicarPolicy(string $tabela, string $coluna, string $var): void
    {
        DB::statement("ALTER TABLE {$tabela} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$tabela} FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
        DB::statement(
            "CREATE POLICY tenant_isolation ON {$tabela}
             USING (
                 nullif(current_setting('{$var}', true), '') IS NULL
                 OR {$coluna} = nullif(current_setting('{$var}', true), '')::int
             )
             WITH CHECK (
                 nullif(current_setting('{$var}', true), '') IS NULL
                 OR {$coluna} = nullif(current_setting('{$var}', true), '')::int
             )"
        );
    }
};
