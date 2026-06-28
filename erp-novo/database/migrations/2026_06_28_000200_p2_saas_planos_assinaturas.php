<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P2 — Camada SaaS: PLANOS, ASSINATURAS e FEATURE-FLAGS por empresa.
 *
 * Transforma o ERP multi-empresa em produto SaaS:
 *  - planos (GLOBAL): catálogo de planos vendáveis (nome/slug/preço). Sem
 *    empresa_id/grupo_id → fica FORA da RLS por natureza (catálogo público da
 *    plataforma); a auto-descoberta da RLS (..._000300) nem o toca.
 *  - plano_recurso (GLOBAL): quais recursos cada plano libera (feature-flags).
 *    Chave do recurso = string do RecursoCatalogo (ex.: 'marketplace').
 *  - assinaturas (TENANT): a assinatura ATIVA da empresa a um plano, com status
 *    (trial/ativa/inadimplente/cancelada) e datas. empresa_id → entra na RLS
 *    (aplicada aqui, pois a ..._000300 já rodou).
 *  - recurso_overrides (TENANT): liga/desliga um recurso pontualmente por empresa,
 *    sobrepondo o plano (ex.: cortesia, piloto). empresa_id → RLS.
 *  - assinatura_eventos (TENANT): trilha de mudanças de plano/status. empresa_id → RLS.
 *
 * NO-OP de RLS fora do PostgreSQL (sqlite em teste). Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Catálogo de planos (GLOBAL) ──
        Schema::create('planos', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();      // ex.: 'basico', 'pro', 'enterprise'
            $t->string('nome');
            $t->string('descricao')->nullable();
            $t->decimal('preco_mensal', 12, 2)->default(0);
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });

        // Recursos liberados por plano (feature-flags). Chave = RecursoCatalogo.
        Schema::create('plano_recurso', function (Blueprint $t) {
            $t->id();
            $t->foreignId('plano_id')->constrained('planos')->cascadeOnDelete();
            $t->string('recurso_chave');       // ex.: 'marketplace', 'tempo_real'
            $t->timestamps();
            $t->unique(['plano_id', 'recurso_chave']);
        });

        // ── Assinatura da empresa (TENANT) ──
        Schema::create('assinaturas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('plano_id')->constrained('planos')->restrictOnDelete();
            // trial | ativa | inadimplente | cancelada
            $t->string('status', 20)->default('trial');
            $t->date('inicio')->nullable();
            $t->date('fim')->nullable();              // null = sem término definido
            $t->date('trial_ate')->nullable();
            $t->timestamps();
            // Uma assinatura corrente por empresa (a aplicação garante 1 ativa; o
            // índice ajuda a consulta por empresa).
            $t->index(['empresa_id', 'status']);
        });

        // Override de recurso por empresa (sobrepõe o plano).
        Schema::create('recurso_overrides', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->string('recurso_chave');
            $t->boolean('habilitado')->default(true);
            $t->timestamps();
            $t->unique(['empresa_id', 'recurso_chave']);
        });

        // Trilha de eventos da assinatura (auditoria de plano/status).
        Schema::create('assinatura_eventos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('assinatura_id')->nullable()->constrained('assinaturas')->nullOnDelete();
            $t->string('tipo', 40);            // 'criada' | 'plano.alterado' | 'status.alterado' | 'cancelada'
            $t->json('detalhes')->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('criado_em')->useCurrent();
            $t->index(['empresa_id', 'criado_em']);
        });

        // RLS para as tabelas TENANT (as globais ficam fora por não terem empresa_id).
        $this->aplicarRls(['assinaturas', 'recurso_overrides', 'assinatura_eventos']);
    }

    public function down(): void
    {
        $this->removerRls(['assinaturas', 'recurso_overrides', 'assinatura_eventos']);
        Schema::dropIfExists('assinatura_eventos');
        Schema::dropIfExists('recurso_overrides');
        Schema::dropIfExists('assinaturas');
        Schema::dropIfExists('plano_recurso');
        Schema::dropIfExists('planos');
    }

    /**
     * Aplica RLS + policy tenant_isolation por empresa_id (mesma regra da
     * ..._000300). NO-OP fora do pgsql. Idempotente.
     *
     * @param  list<string>  $tabelas
     */
    private function aplicarRls(array $tabelas): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($tabelas as $tabela) {
            DB::statement("ALTER TABLE {$tabela} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$tabela} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
            DB::statement(
                "CREATE POLICY tenant_isolation ON {$tabela}
                 USING (
                     nullif(current_setting('app.empresa_id', true), '') IS NULL
                     OR empresa_id = current_setting('app.empresa_id', true)::int
                 )
                 WITH CHECK (
                     nullif(current_setting('app.empresa_id', true), '') IS NULL
                     OR empresa_id = current_setting('app.empresa_id', true)::int
                 )"
            );
        }
    }

    /** @param  list<string>  $tabelas */
    private function removerRls(array $tabelas): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($tabelas as $tabela) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
            DB::statement("ALTER TABLE {$tabela} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$tabela} DISABLE ROW LEVEL SECURITY");
        }
    }
};
