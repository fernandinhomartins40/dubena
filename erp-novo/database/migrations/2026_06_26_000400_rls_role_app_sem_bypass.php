<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RLS — role de aplicação SEM superuser/bypassrls (fecha o furo crítico).
 *
 * PROBLEMA: o PostgreSQL IGNORA Row-Level Security para roles SUPERUSER ou com
 * BYPASSRLS — mesmo com FORCE ROW LEVEL SECURITY. Se a aplicação conectar com uma
 * role assim, TODAS as policies de isolamento são silenciosamente ignoradas e a 2ª
 * barreira não protege nada. (Foi o que a auditoria encontrou: o app conectava como
 * superuser.)
 *
 * SOLUÇÃO: criar uma role dedicada `erp_app` — LOGIN, NOSUPERUSER, NOBYPASSRLS —
 * com privilégios apenas de dados (SELECT/INSERT/UPDATE/DELETE + uso de sequences),
 * inclusive em tabelas FUTURAS (ALTER DEFAULT PRIVILEGES). A aplicação deve conectar
 * com ela (DB_USERNAME=erp_app); as MIGRATIONS continuam rodando com a role dona
 * (que cria tabelas/policies). Assim a RLS passa a valer para o tráfego web.
 *
 * A senha vem de RLS_APP_DB_PASSWORD (env). Sem ela, a migration é NO-OP segura:
 * não cria a role pela metade (evita role sem senha). NO-OP fora do PostgreSQL.
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $senha = env('RLS_APP_DB_PASSWORD');
        if (empty($senha)) {
            // Sem senha definida não criamos a role (não deixar role sem credencial).
            // Defina RLS_APP_DB_PASSWORD no ambiente e rode a migration novamente.
            return;
        }

        $role = 'erp_app';
        $senhaSql = str_replace("'", "''", (string) $senha);
        $owner = DB::selectOne('SELECT current_user AS u')->u;

        // Cria (ou atualiza a senha) da role de app — sem superuser, sem bypassrls.
        DB::statement(<<<SQL
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '{$role}') THEN
                    CREATE ROLE {$role} LOGIN PASSWORD '{$senhaSql}' NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE;
                ELSE
                    ALTER ROLE {$role} WITH LOGIN PASSWORD '{$senhaSql}' NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE;
                END IF;
            END
            \$\$;
        SQL);

        // Privilégios de dados nas tabelas/sequences ATUAIS.
        DB::statement("GRANT USAGE ON SCHEMA public TO {$role}");
        DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO {$role}");
        DB::statement("GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO {$role}");

        // Privilégios para tabelas/sequences FUTURAS (criadas pela role dona nas
        // próximas migrations) — evita ter de re-grantar a cada migration nova.
        DB::statement("ALTER DEFAULT PRIVILEGES FOR ROLE {$owner} IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO {$role}");
        DB::statement("ALTER DEFAULT PRIVILEGES FOR ROLE {$owner} IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO {$role}");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        if (! DB::selectOne("SELECT 1 AS x FROM pg_roles WHERE rolname = 'erp_app'")) {
            return;
        }

        $role = 'erp_app';
        $owner = DB::selectOne('SELECT current_user AS u')->u;

        DB::statement("ALTER DEFAULT PRIVILEGES FOR ROLE {$owner} IN SCHEMA public REVOKE SELECT, INSERT, UPDATE, DELETE ON TABLES FROM {$role}");
        DB::statement("ALTER DEFAULT PRIVILEGES FOR ROLE {$owner} IN SCHEMA public REVOKE USAGE, SELECT ON SEQUENCES FROM {$role}");
        DB::statement("REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM {$role}");
        DB::statement("REVOKE ALL ON ALL TABLES IN SCHEMA public FROM {$role}");
        DB::statement("REVOKE USAGE ON SCHEMA public FROM {$role}");
        DB::statement("DROP ROLE IF EXISTS {$role}");
    }
};
