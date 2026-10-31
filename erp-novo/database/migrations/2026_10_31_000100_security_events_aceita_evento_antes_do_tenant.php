<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PIE P2-D-01 — `security_events` aceita o evento gravado antes do tenant.
 *
 * O login acontece ANTES do envelope de tenant: o evento `login.suporte` sai
 * com `tenant_account_id` nulo, e a policy canônica (`WITH CHECK
 * app_tenant_can_operate(...)`, com FORCE RLS) recusava o insert. Resultado na
 * execução real: todo login de usuário `support` dava HTTP 500 — e é esse o
 * admin que o `DeployAdminSeeder` cria em toda instalação nova.
 *
 * Mesma decisão de `ponte_usos`/`integracao_consumos`: a linha sem tenant não
 * pertence a revenda nenhuma, então gravá-la não viola sigilo; o `USING`
 * continua escondendo-a de toda revenda (quem a lê é a plataforma, como owner),
 * e gravar linha de OUTRA revenda segue barrado.
 *
 * Engolir a exceção no registro NÃO era opção: o evento é a trilha de quando o
 * bypass total de permissão entrou em uso, e ele sumiria em silêncio.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON security_events');
        DB::statement(
            'CREATE POLICY tenant_isolation ON security_events
             USING (app_tenant_can_read(tenant_account_id, empresa_id))
             WITH CHECK (tenant_account_id IS NULL OR app_tenant_can_operate(tenant_account_id, empresa_id))'
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON security_events');
        DB::statement(
            'CREATE POLICY tenant_isolation ON security_events
             USING (app_tenant_can_read(tenant_account_id, empresa_id))
             WITH CHECK (app_tenant_can_operate(tenant_account_id, empresa_id))'
        );
    }
};
