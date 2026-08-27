<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F1-03: expansao aditiva da chave de grant.
 *
 * As colunas permanecem nulas durante a transicao: nenhum tenant e deduzido de
 * grupo_id, empresa_id ou membership legado. F1-08 torna a combinacao
 * estruturalmente consistente e F1-10 preenche somente vinculos aprovados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_company_grants', function (Blueprint $table) {
            $table->foreignId('tenant_account_id')
                ->nullable()
                ->after('tenant_membership_id')
                ->constrained('tenant_accounts')
                ->restrictOnDelete();
            $table->foreignId('tenant_company_id')
                ->nullable()
                ->after('empresa_id')
                ->constrained('tenant_companies')
                ->restrictOnDelete();
            $table->index(['tenant_account_id', 'empresa_id'], 'tenant_company_grants_tenant_empresa_index');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_company_grants', function (Blueprint $table) {
            $table->dropIndex('tenant_company_grants_tenant_empresa_index');
            $table->dropConstrainedForeignId('tenant_company_id');
            $table->dropConstrainedForeignId('tenant_account_id');
        });
    }
};
