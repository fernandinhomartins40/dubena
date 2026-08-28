<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** F1-08: FKs financeiras nao podem atravessar a fronteira de tenant. */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION public.app_enforce_financial_configuration_tenant()
RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
    configuration_tenant_account_id bigint;
BEGIN
    IF NEW.planoconta_id IS NOT NULL THEN
        SELECT tenant_account_id INTO configuration_tenant_account_id
          FROM public.planos_conta WHERE id = NEW.planoconta_id;
        IF configuration_tenant_account_id IS NOT NULL
           AND (NEW.tenant_account_id IS NULL OR NEW.tenant_account_id <> configuration_tenant_account_id) THEN
            RAISE EXCEPTION 'F1 recusada: %.planoconta_id precisa referenciar configuracao do mesmo tenant', TG_TABLE_NAME
                USING ERRCODE = '23514';
        END IF;
    END IF;

    IF NEW.centrocusto_id IS NOT NULL THEN
        SELECT tenant_account_id INTO configuration_tenant_account_id
          FROM public.centros_custo WHERE id = NEW.centrocusto_id;
        IF configuration_tenant_account_id IS NOT NULL
           AND (NEW.tenant_account_id IS NULL OR NEW.tenant_account_id <> configuration_tenant_account_id) THEN
            RAISE EXCEPTION 'F1 recusada: %.centrocusto_id precisa referenciar configuracao do mesmo tenant', TG_TABLE_NAME
                USING ERRCODE = '23514';
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS financeiros_configuration_tenant ON public.financeiros;
CREATE TRIGGER financeiros_configuration_tenant
BEFORE INSERT OR UPDATE OF tenant_account_id, planoconta_id, centrocusto_id ON public.financeiros
FOR EACH ROW EXECUTE FUNCTION public.app_enforce_financial_configuration_tenant();

DROP TRIGGER IF EXISTS financeirorateios_configuration_tenant ON public.financeirorateios;
CREATE TRIGGER financeirorateios_configuration_tenant
BEFORE INSERT OR UPDATE OF tenant_account_id, planoconta_id, centrocusto_id ON public.financeirorateios
FOR EACH ROW EXECUTE FUNCTION public.app_enforce_financial_configuration_tenant();
SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS financeiros_configuration_tenant ON public.financeiros;
DROP TRIGGER IF EXISTS financeirorateios_configuration_tenant ON public.financeirorateios;
DROP FUNCTION IF EXISTS public.app_enforce_financial_configuration_tenant();
SQL);
    }
};
