<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RLS COMPLETA (PostgreSQL) — isolamento multi-tenant à prova de vazamento.
 *
 * Substitui a F02.5 (lista hard-coded que drifava): aqui as tabelas são
 * DESCOBERTAS em runtime pela presença das colunas, então qualquer tabela
 * futura com empresa_id/grupo_id passa a ser isolada automaticamente — sem
 * precisar editar lista nenhuma. É a 2ª barreira (a 1ª é o global scope da app).
 *
 * Três camadas:
 *  1. empresa_id  → policy por empresa (current_setting('app.empresa_id')).
 *  2. grupo_id SEM empresa_id → policy por grupo (current_setting('app.grupo_id')).
 *     São os cadastros de apoio compartilhados entre as empresas de um grupo.
 *  3. tabela `empresas` → caso especial: filtrada por grupo_id (uma rede só
 *     enxerga as próprias empresas).
 *
 * Tabelas com AMBAS as colunas são isoladas por empresa_id (mais restritivo) —
 * não recebem a policy de grupo para não conflitar.
 *
 * Sem variável setada (CLI/ETL/seed/crons globais), current_setting(...,true) é
 * NULL e a policy não restringe — espelha o global scope sem tenant. As variáveis
 * SEMPRE são setadas em requisições web (ResolveTenant), então o isolamento web
 * é total. FORCE garante que a policy valha até para o owner usado pela app.
 *
 * NO-OP fora do PostgreSQL (sqlite em teste). Idempotente (DROP POLICY IF EXISTS).
 */
return new class extends Migration
{
    /**
     * Tabelas que NÃO devem ser isoladas mesmo tendo grupo_id/empresa_id.
     * Isolar estas quebraria autenticação, RBAC ou a raiz do tenancy.
     *
     * @var list<string>
     */
    private array $allowlist = [
        'grupos',          // raiz do tenancy; filtrar a si própria quebra resolução
        'users',           // login precisa achar o usuário ANTES de haver tenant
        'role_user',       // pivot de RBAC (já carrega empresa_id no pivot p/ filtro app)
        'permission_role', // pivot RBAC global
        'empresa_user',    // pivot de multi-empresa (define o que o user PODE acessar)
        'empresa_configs', // resolvido por empresa_id explícito no controller; pivot 1:1
        'roles',           // papéis podem ser globais (grupo_id nulo) — filtro no app
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Remove as policies antigas da F02.5 para reconstruir de forma uniforme.
        $this->limparPoliciesAntigas();

        foreach ($this->tabelas('empresa_id') as $tabela) {
            $this->aplicarPolicy($tabela, 'empresa_id', 'app.empresa_id');
        }

        // grupo_id apenas onde NÃO há empresa_id (senão a de empresa já cobre).
        $comEmpresa = $this->tabelas('empresa_id');
        foreach ($this->tabelas('grupo_id') as $tabela) {
            if (in_array($tabela, $comEmpresa, true)) {
                continue;
            }
            $this->aplicarPolicy($tabela, 'grupo_id', 'app.grupo_id');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_unique([...$this->tabelas('empresa_id'), ...$this->tabelas('grupo_id')]) as $tabela) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
            DB::statement("ALTER TABLE {$tabela} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$tabela} DISABLE ROW LEVEL SECURITY");
        }
    }

    /**
     * Tabelas (não-allowlist) que têm a coluna informada. Descoberto em runtime.
     *
     * @return list<string>
     */
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

    /**
     * Aplica RLS + policy de isolamento numa tabela, escopada pela coluna/variável.
     *
     * A policy permite a linha quando: não há tenant setado (CLI/ETL), OU a coluna
     * casa com a variável de sessão. WITH CHECK impede gravar linha de outro tenant.
     */
    private function aplicarPolicy(string $tabela, string $coluna, string $var): void
    {
        DB::statement("ALTER TABLE {$tabela} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$tabela} FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
        DB::statement(
            "CREATE POLICY tenant_isolation ON {$tabela}
             USING (
                 nullif(current_setting('{$var}', true), '') IS NULL
                 OR {$coluna} = current_setting('{$var}', true)::int
             )
             WITH CHECK (
                 nullif(current_setting('{$var}', true), '') IS NULL
                 OR {$coluna} = current_setting('{$var}', true)::int
             )"
        );
    }

    /** Remove qualquer policy tenant_isolation pré-existente (reconstrução limpa). */
    private function limparPoliciesAntigas(): void
    {
        $tabelas = DB::table('pg_policies')
            ->where('policyname', 'tenant_isolation')
            ->pluck('tablename')
            ->all();

        foreach ($tabelas as $t) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$t}");
        }
    }
};
