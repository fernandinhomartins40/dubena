<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * L1 — Bloqueio temporário de entregador na distribuição. Um entregador bloqueado
 * não recebe novas atribuições (manuais ou automáticas) até `ate`. Motivo
 * auditável. Tenant-scoped → RLS inline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entregador_bloqueios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('entregador_user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('operador_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('motivo', 255)->nullable();
            $t->dateTime('ate')->nullable(); // null = indeterminado (até desbloquear)
            $t->boolean('ativo')->default(true);
            $t->timestamps();

            $t->index(['empresa_id', 'entregador_user_id', 'ativo']);
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE entregador_bloqueios ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE entregador_bloqueios FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON entregador_bloqueios');
        DB::statement(
            "CREATE POLICY tenant_isolation ON entregador_bloqueios
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
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON entregador_bloqueios');
        }
        Schema::dropIfExists('entregador_bloqueios');
    }
};
