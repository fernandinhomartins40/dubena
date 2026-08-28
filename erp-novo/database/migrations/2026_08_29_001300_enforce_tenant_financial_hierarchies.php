<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F1-08: uma hierarquia financeira protegida nao pode ligar pai e filho de
 * tenants distintos. A excecao transitória de duas chaves nulas existe apenas
 * para que o deploy anteceda o backfill documental, sem atribuir ownership.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Esta tabela e configuracao por grupo e ficou fora da lista inicial
        // COMPANY. A expansao e aditiva: nao atribui tenant nem ativa policy.
        if (! Schema::hasColumn('planos_conta', 'tenant_account_id')) {
            Schema::table('planos_conta', function (Blueprint $table) {
                $table->foreignId('tenant_account_id')
                    ->nullable()
                    ->constrained('tenant_accounts')
                    ->restrictOnDelete();
                $table->index('tenant_account_id');
            });
        }

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION public.app_enforce_tenant_financial_hierarchy()
RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
    parent_tenant_account_id bigint;
BEGIN
    IF NEW.pai_id IS NULL THEN
        RETURN NEW;
    END IF;

    EXECUTE format('SELECT tenant_account_id FROM public.%I WHERE id = $1', TG_TABLE_NAME)
       INTO parent_tenant_account_id
      USING NEW.pai_id;

    -- O legado sem ponte documental continua inelegivel para RLS, mas o deploy
    -- nao inventa ownership antes do comando de conversao aprovado.
    IF NEW.tenant_account_id IS NULL AND parent_tenant_account_id IS NULL THEN
        RETURN NEW;
    END IF;

    IF NEW.tenant_account_id IS NULL
       OR parent_tenant_account_id IS NULL
       OR NEW.tenant_account_id <> parent_tenant_account_id THEN
        RAISE EXCEPTION 'F1 recusada: %.pai_id precisa referenciar registro do mesmo tenant', TG_TABLE_NAME
            USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS centros_custo_tenant_hierarchy ON public.centros_custo;
CREATE TRIGGER centros_custo_tenant_hierarchy
BEFORE INSERT OR UPDATE OF pai_id, tenant_account_id ON public.centros_custo
FOR EACH ROW EXECUTE FUNCTION public.app_enforce_tenant_financial_hierarchy();

DROP TRIGGER IF EXISTS planos_conta_tenant_hierarchy ON public.planos_conta;
CREATE TRIGGER planos_conta_tenant_hierarchy
BEFORE INSERT OR UPDATE OF pai_id, tenant_account_id ON public.planos_conta
FOR EACH ROW EXECUTE FUNCTION public.app_enforce_tenant_financial_hierarchy();
SQL);
    }

    public function down(): void
    {
        if (Schema::hasColumn('planos_conta', 'tenant_account_id')) {
            Schema::table('planos_conta', function (Blueprint $table) {
                $table->dropIndex(['tenant_account_id']);
                $table->dropConstrainedForeignId('tenant_account_id');
            });
        }

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS centros_custo_tenant_hierarchy ON public.centros_custo;
DROP TRIGGER IF EXISTS planos_conta_tenant_hierarchy ON public.planos_conta;
DROP FUNCTION IF EXISTS public.app_enforce_tenant_financial_hierarchy();
SQL);
    }
};
