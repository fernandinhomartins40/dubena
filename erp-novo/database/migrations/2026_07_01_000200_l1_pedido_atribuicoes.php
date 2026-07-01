<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * L1 — Auditoria de atribuição de entregas. Cada linha é uma decisão logística
 * (atribuir/redistribuir): quem operou, de qual entregador para qual, com qual
 * veículo, o motivo e se foi manual ou automática (L3). Trilha imutável para a
 * Central e para relatórios de SLA.
 *
 * Tenant-scoped (empresa_id) → RLS inline. NO-OP fora do PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_atribuicoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            // De → Para (nullable: 1ª atribuição não tem "de"; desatribuição não tem "para").
            $t->foreignId('de_entregador_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('para_entregador_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('veiculo_id')->nullable()->constrained('monitora_veiculos')->nullOnDelete();
            // Quem operou (operador da central) — null quando automático (L3).
            $t->foreignId('operador_user_id')->nullable()->constrained('users')->nullOnDelete();
            // 'atribuir' | 'redistribuir' | 'desatribuir'
            $t->string('acao', 20);
            $t->boolean('automatico')->default(false);
            $t->string('motivo', 255)->nullable();
            $t->timestamps();

            $t->index(['empresa_id', 'pedido_id']);
            $t->index(['empresa_id', 'para_entregador_user_id']);
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE pedido_atribuicoes ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE pedido_atribuicoes FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON pedido_atribuicoes');
        DB::statement(
            "CREATE POLICY tenant_isolation ON pedido_atribuicoes
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

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON pedido_atribuicoes');
        }
        Schema::dropIfExists('pedido_atribuicoes');
    }
};
