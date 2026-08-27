<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** F1-08: impede parentes de tenants distintos no grant explicito. */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION public.app_validate_tenant_company_grant()
RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    -- F1-10 ainda pode ter registros transicionais nulos; nao inferir valores.
    IF NEW.tenant_account_id IS NULL OR NEW.tenant_company_id IS NULL THEN
        RETURN NEW;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM public.tenant_memberships membership
        WHERE membership.id = NEW.tenant_membership_id
          AND membership.tenant_account_id = NEW.tenant_account_id
    ) THEN
        RAISE EXCEPTION 'membership fora da tenant_account do grant';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM public.tenant_companies company
        WHERE company.id = NEW.tenant_company_id
          AND company.tenant_account_id = NEW.tenant_account_id
          AND company.empresa_id = NEW.empresa_id
    ) THEN
        RAISE EXCEPTION 'empresa ou tenant_company fora da tenant_account do grant';
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS tenant_company_grant_consistency ON public.tenant_company_grants;
CREATE TRIGGER tenant_company_grant_consistency
BEFORE INSERT OR UPDATE ON public.tenant_company_grants
FOR EACH ROW EXECUTE FUNCTION public.app_validate_tenant_company_grant();
SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS tenant_company_grant_consistency ON public.tenant_company_grants;
DROP FUNCTION IF EXISTS public.app_validate_tenant_company_grant();
SQL);
    }
};
