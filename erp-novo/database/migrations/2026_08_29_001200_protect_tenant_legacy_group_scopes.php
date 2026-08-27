<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** A ponte documental e dado de fronteira: runtime le somente a propria conta. */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql' || ! Schema::hasTable('tenant_legacy_group_scopes')) {
            return;
        }

        DB::statement('ALTER TABLE tenant_legacy_group_scopes ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE tenant_legacy_group_scopes FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON tenant_legacy_group_scopes');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON tenant_legacy_group_scopes
            USING (app_current_tenant_account_id() = tenant_account_id)
            WITH CHECK (false)
        SQL);
    }

    public function down(): void
    {
        // Nunca reabre uma tabela de fronteira de tenant no rollback automatico.
    }
};
