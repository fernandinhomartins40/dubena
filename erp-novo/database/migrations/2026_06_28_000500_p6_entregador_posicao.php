<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P6 — Rastreamento do entregador em tempo real: SNAPSHOT da última posição.
 *
 * Espelha `monitora_ultima_posicao` (que é por VEÍCULO/GPS externo), mas por
 * ENTREGADOR (user) e alimentado pelo APP do entregador. 1 linha por entregador
 * (snapshot leve para leitura rápida); o trajeto contínuo, quando necessário, vai
 * para Redis (TTL) — não poluímos o banco com cada ping.
 *
 * Tenant-scoped (empresa_id) → RLS aplicada aqui (a ..._000300 já rodou). A posição
 * só existe e só é publicada para entregas ativas; cessa ao concluir (privacidade).
 *
 * NO-OP de RLS fora do PostgreSQL (sqlite em teste). Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entregador_posicoes_ultima', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('entregador_user_id')->constrained('users')->cascadeOnDelete();
            $t->decimal('latitude', 10, 7);
            $t->decimal('longitude', 10, 7);
            $t->decimal('velocidade', 6, 2)->nullable();   // km/h (opcional)
            $t->unsignedSmallInteger('direcao')->nullable(); // graus 0-359
            $t->dateTime('atualizado_em');
            $t->timestamps();
            $t->unique('entregador_user_id'); // 1 snapshot por entregador
            $t->index(['empresa_id', 'atualizado_em']);
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE entregador_posicoes_ultima ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE entregador_posicoes_ultima FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON entregador_posicoes_ultima');
        DB::statement(
            "CREATE POLICY tenant_isolation ON entregador_posicoes_ultima
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
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON entregador_posicoes_ultima');
            DB::statement('ALTER TABLE entregador_posicoes_ultima NO FORCE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE entregador_posicoes_ultima DISABLE ROW LEVEL SECURITY');
        }
        Schema::dropIfExists('entregador_posicoes_ultima');
    }
};
