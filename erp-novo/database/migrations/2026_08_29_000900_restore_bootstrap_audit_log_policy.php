<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Login e auditoria de segurança podem ocorrer antes de o TenantEnvelope
 * existir. Essas tabelas não são dados operacionais e já eram allowlist RLS;
 * restauramos a política técnica em vez de abrir qualquer tabela de negócio.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['audit_logs', 'login_logs'] as $table) {
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
        }
    }

    public function down(): void
    {
        // Não recria uma policy inadequada ao bootstrap de autenticação.
    }
};
