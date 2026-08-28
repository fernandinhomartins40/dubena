<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F1-06/F1-10: converte configuracoes ainda compartilhadas por grupo somente
 * depois de uma ponte documental explicita. grupo_id permanece um detalhe de
 * compatibilidade do cadastro, jamais uma fronteira que determine o tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_legacy_group_scopes')) {
            Schema::create('tenant_legacy_group_scopes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_account_id')->constrained('tenant_accounts')->restrictOnDelete();
                $table->foreignId('grupo_id')->unique()->constrained('grupos')->restrictOnDelete();
                $table->string('status', 32)->default('PENDING')->index();
                $table->timestamp('approved_at')->nullable();
                $table->string('evidence_ref');
                $table->timestamps();
            });
        }

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION public.app_tenant_can_read_group_config(target_tenant_account_id bigint, target_grupo_id bigint)
RETURNS boolean LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public, pg_temp AS $$
    SELECT public.app_current_tenant_account_id() = target_tenant_account_id
       AND EXISTS (
            SELECT 1
            FROM public.tenant_company_grants tenant_grant
            JOIN public.empresas empresa ON empresa.id = tenant_grant.empresa_id
            WHERE tenant_grant.tenant_account_id = target_tenant_account_id
              AND tenant_grant.tenant_membership_id = public.app_current_tenant_membership_id()
              AND tenant_grant.can_read = true
              AND tenant_grant.approved_at IS NOT NULL
              AND empresa.grupo_id = target_grupo_id
       )
$$;

CREATE OR REPLACE FUNCTION public.app_tenant_can_operate_group_config(target_tenant_account_id bigint, target_grupo_id bigint)
RETURNS boolean LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public, pg_temp AS $$
    SELECT public.app_current_tenant_account_id() = target_tenant_account_id
       AND EXISTS (
            SELECT 1
            FROM public.tenant_company_grants tenant_grant
            JOIN public.empresas empresa ON empresa.id = tenant_grant.empresa_id
            WHERE tenant_grant.tenant_account_id = target_tenant_account_id
              AND tenant_grant.tenant_membership_id = public.app_current_tenant_membership_id()
              AND tenant_grant.can_operate = true
              AND tenant_grant.approved_at IS NOT NULL
              AND empresa.grupo_id = target_grupo_id
       )
$$;
SQL);

        // Backfill e policy nao pertencem a migration: sem uma ponte documental
        // preexistente, ativar RLS aqui ocultaria configuracoes legadas no deploy.
        // O comando saas:tenant:proteger-configuracao-grupo confere cobertura
        // total e somente entao executa essa mudanca irreversivel de fronteira.
    }

    public function down(): void
    {
        // Nao remove chaves nem reabre policies. A ponte so e removivel apos contract.
    }
};
