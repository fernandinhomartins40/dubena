<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F1-01: raiz declarativa da fronteira SaaS.
 *
 * Estas tabelas nascem vazias de propósito. Elas não convertem grupo_id,
 * empresa_user, empresa_id do usuário ou qualquer outro vínculo legado em
 * titularidade. A criação dos vínculos aprovados é tratada no F1-10.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name');
            $table->string('document', 32)->nullable()->unique();
            $table->string('status', 32)->default('OWNERSHIP_UNRESOLVED')->index();
            $table->timestamp('classified_at')->nullable();
            $table->string('classification_evidence_ref')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_account_id')->constrained('tenant_accounts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('PENDING')->index();
            $table->string('membership_role', 64)->default('MEMBER');
            $table->timestamp('approved_at')->nullable();
            $table->string('approval_evidence_ref')->nullable();
            $table->timestamps();

            $table->unique(['tenant_account_id', 'user_id']);
        });

        Schema::create('tenant_companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_account_id')->constrained('tenant_accounts')->restrictOnDelete();
            $table->foreignId('empresa_id')->unique()->constrained('empresas')->restrictOnDelete();
            $table->string('status', 32)->default('PENDING_OWNERSHIP')->index();
            $table->timestamp('approved_at')->nullable();
            $table->string('ownership_evidence_ref')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_company_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_membership_id')->constrained('tenant_memberships')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->boolean('can_read')->default(false);
            $table->boolean('can_operate')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->string('grant_evidence_ref')->nullable();
            $table->timestamps();

            $table->unique(['tenant_membership_id', 'empresa_id']);
        });

        // Vínculo comercial nunca é permissão implícita de leitura/operação.
        Schema::create('tenant_network_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_tenant_account_id')->constrained('tenant_accounts')->restrictOnDelete();
            $table->foreignId('consumer_tenant_account_id')->constrained('tenant_accounts')->restrictOnDelete();
            $table->string('relationship_type', 64);
            $table->string('status', 32)->default('PENDING')->index();
            $table->string('terms_reference')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->unique([
                'provider_tenant_account_id',
                'consumer_tenant_account_id',
                'relationship_type',
            ], 'tenant_network_links_pair_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_network_links');
        Schema::dropIfExists('tenant_company_grants');
        Schema::dropIfExists('tenant_companies');
        Schema::dropIfExists('tenant_memberships');
        Schema::dropIfExists('tenant_accounts');
    }
};
