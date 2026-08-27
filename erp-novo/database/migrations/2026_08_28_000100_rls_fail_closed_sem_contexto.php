<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Torna a role de runtime fail-closed quando o contexto RLS não foi resolvido.
 *
 * Migrations anteriores permitiam todas as linhas quando as GUCs estavam
 * ausentes para acomodar CLI/ETL. Isso transforma qualquer consumidor que
 * esqueça o contexto em bypass total. Tarefas de plataforma/DDL devem usar a
 * conexão `pgsql_owner`; a conexão `pgsql` de runtime nunca ganha bypass.
 */
return new class extends Migration
{
    /** @var list<string> */
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

        $comEmpresa = $this->tabelas('empresa_id');
        foreach ($comEmpresa as $tabela) {
            $this->aplicarEmpresa($tabela);
        }

        foreach ($this->tabelas('grupo_id') as $tabela) {
            if (! in_array($tabela, $comEmpresa, true)) {
                $this->aplicarGrupo($tabela);
            }
        }
    }

    public function down(): void
    {
        // Segurança não regride para o contrato fail-open anterior.
    }

    /** @return list<string> */
    private function tabelas(string $coluna): array
    {
        return DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('column_name', $coluna)
            ->pluck('table_name')
            ->filter(fn (string $tabela) => ! in_array($tabela, $this->allowlist, true)
                && Schema::hasTable($tabela))
            ->values()
            ->all();
    }

    private function aplicarEmpresa(string $tabela): void
    {
        DB::statement("ALTER TABLE {$tabela} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$tabela} FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
        DB::statement(
            "CREATE POLICY tenant_isolation ON {$tabela}
             USING (
                 nullif(current_setting('app.empresa_id', true), '') IS NOT NULL
                 AND (
                     (
                         nullif(current_setting('app.empresas_visiveis', true), '') IS NOT NULL
                         AND empresa_id = ANY (
                             string_to_array(current_setting('app.empresas_visiveis', true), ',')::int[]
                         )
                     )
                     OR (
                         nullif(current_setting('app.empresas_visiveis', true), '') IS NULL
                         AND empresa_id = nullif(current_setting('app.empresa_id', true), '')::int
                     )
                 )
             )
             WITH CHECK (
                 nullif(current_setting('app.empresa_id', true), '') IS NOT NULL
                 AND empresa_id = nullif(current_setting('app.empresa_id', true), '')::int
             )"
        );
    }

    private function aplicarGrupo(string $tabela): void
    {
        DB::statement("ALTER TABLE {$tabela} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$tabela} FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
        DB::statement(
            "CREATE POLICY tenant_isolation ON {$tabela}
             USING (
                 nullif(current_setting('app.grupo_id', true), '') IS NOT NULL
                 AND grupo_id = nullif(current_setting('app.grupo_id', true), '')::int
             )
             WITH CHECK (
                 nullif(current_setting('app.grupo_id', true), '') IS NOT NULL
                 AND grupo_id = nullif(current_setting('app.grupo_id', true), '')::int
             )"
        );
    }
};
