<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION public.app_enforce_tenant_financial_hierarchy()
RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE parent_tenant_account_id bigint;
BEGIN
    IF NEW.pai_id IS NULL THEN RETURN NEW; END IF;
    EXECUTE format('SELECT tenant_account_id FROM public.%I WHERE id = $1', TG_TABLE_NAME) INTO parent_tenant_account_id USING NEW.pai_id;
    IF current_setting('app.tenant_hierarchy_backfill', true) = '1' THEN RETURN NEW; END IF;
    IF NEW.tenant_account_id IS NULL AND parent_tenant_account_id IS NULL THEN RETURN NEW; END IF;
    IF NEW.tenant_account_id IS NULL OR parent_tenant_account_id IS NULL OR NEW.tenant_account_id <> parent_tenant_account_id THEN
        RAISE EXCEPTION 'F1 recusada: %.pai_id precisa referenciar registro do mesmo tenant', TG_TABLE_NAME USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$;
SQL);
    }

    public function down(): void {}
};
