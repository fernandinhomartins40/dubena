<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F1-06: funcoes unicas para policies SaaS. As policies existentes continuam
 * em modo legado ate F1-10 preencher vinculos aprovados e autorizar o switch.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION public.app_current_tenant_account_id()
RETURNS bigint LANGUAGE sql STABLE AS $$
    SELECT NULLIF(current_setting('app.tenant_account_id', true), '')::bigint
$$;

CREATE OR REPLACE FUNCTION public.app_current_tenant_membership_id()
RETURNS bigint LANGUAGE sql STABLE AS $$
    SELECT NULLIF(current_setting('app.tenant_membership_id', true), '')::bigint
$$;

CREATE OR REPLACE FUNCTION public.app_tenant_can_read(target_tenant_account_id bigint, target_empresa_id bigint)
RETURNS boolean LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public, pg_temp AS $$
    SELECT public.app_current_tenant_account_id() = target_tenant_account_id
       AND EXISTS (
            SELECT 1
            FROM public.tenant_company_grants tenant_grant
            WHERE tenant_grant.tenant_membership_id = public.app_current_tenant_membership_id()
              AND tenant_grant.tenant_account_id = target_tenant_account_id
              AND tenant_grant.empresa_id = target_empresa_id
              AND tenant_grant.can_read = true
              AND tenant_grant.approved_at IS NOT NULL
       )
$$;

CREATE OR REPLACE FUNCTION public.app_tenant_can_operate(target_tenant_account_id bigint, target_empresa_id bigint)
RETURNS boolean LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public, pg_temp AS $$
    SELECT public.app_current_tenant_account_id() = target_tenant_account_id
       AND EXISTS (
            SELECT 1
            FROM public.tenant_company_grants tenant_grant
            WHERE tenant_grant.tenant_membership_id = public.app_current_tenant_membership_id()
              AND tenant_grant.tenant_account_id = target_tenant_account_id
              AND tenant_grant.empresa_id = target_empresa_id
              AND tenant_grant.can_operate = true
              AND tenant_grant.approved_at IS NOT NULL
       )
$$;
SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
DROP FUNCTION IF EXISTS public.app_tenant_can_operate(bigint, bigint);
DROP FUNCTION IF EXISTS public.app_tenant_can_read(bigint, bigint);
DROP FUNCTION IF EXISTS public.app_current_tenant_membership_id();
DROP FUNCTION IF EXISTS public.app_current_tenant_account_id();
SQL);
    }
};
