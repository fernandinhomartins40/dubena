<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Garante os privilégios da role de RUNTIME (`erp_app`) sobre todo o schema.
 *
 * Por que existe como MIGRATION e não como passo manual de deploy:
 *
 * O runtime usa uma role restrita (`erp_app`, NOSUPERUSER/NOBYPASSRLS) para a
 * RLS valer de fato — enquanto migrations rodam pela role dona. Toda tabela
 * nova nasce, portanto, pertencendo ao OWNER, e a role de runtime não tem
 * acesso a ela até receber GRANT. O sintoma é sempre o mesmo e sempre tardio:
 * `SQLSTATE[42501] permission denied for table <nova>` na primeira vez que
 * alguém abre a tela daquele módulo.
 *
 * Já aconteceu duas vezes neste projeto (restore do banco e criação de tabelas
 * pelo ETL). Rodar como migration resolve na origem: qualquer ambiente — VPS,
 * homologação, um tenant novo amanhã — recebe os privilégios ao migrar, sem
 * depender de alguém lembrar de um script.
 *
 * `ALTER DEFAULT PRIVILEGES` cobre o futuro: tabelas criadas DEPOIS desta
 * migration pela mesma role dona já nascem acessíveis. O GRANT explícito cobre
 * o passado (as que já existem).
 *
 * NO-OP fora do Postgres e quando a role não existe (dev/CI de uma role só).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Mesma role de `2026_06_26_000400_rls_role_app_sem_bypass`.
        $role = 'erp_app';
        if (! $this->roleExiste($role)) {
            return;
        }

        DB::statement("GRANT USAGE ON SCHEMA public TO {$role}");
        DB::statement(
            "GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO {$role}"
        );
        // UPDATE na sequence é exigido pelo `setval` que o ETL usa para
        // ressincronizar o auto-increment depois de gravar com id preservado.
        // Sem ele: "permission denied for sequence <tabela>_id_seq".
        DB::statement("GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO {$role}");

        // Tabelas/sequences criadas daqui para a frente já nascem acessíveis.
        DB::statement(
            "ALTER DEFAULT PRIVILEGES IN SCHEMA public
             GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO {$role}"
        );
        DB::statement(
            "ALTER DEFAULT PRIVILEGES IN SCHEMA public
             GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO {$role}"
        );
    }

    public function down(): void
    {
        // Sem revogação: retirar o acesso da role de runtime derruba a
        // aplicação inteira, e não é o que um rollback de schema deve fazer.
    }

    private function roleExiste(string $role): bool
    {
        try {
            return DB::selectOne(
                'SELECT 1 AS existe FROM pg_roles WHERE rolname = ?', [$role]
            ) !== null;
        } catch (\Throwable) {
            return false;
        }
    }
};
