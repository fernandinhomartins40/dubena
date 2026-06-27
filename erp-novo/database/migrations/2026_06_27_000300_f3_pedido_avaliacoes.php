<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F3 — Avaliação de pedido (app do cliente). Porta PedidoAvaliacao do legado
 * (app/Api): nota 1–5 + mensagem (≤140), 1 avaliação por pedido, ou "ignorado"
 * (cliente dispensou avaliar). Escopo por empresa (RLS auto-descoberta cobre).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_avaliacoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('pedido_id')->unique()->constrained('pedidos')->cascadeOnDelete();
            $t->unsignedTinyInteger('rating')->nullable();   // 1..5 (null quando ignorado)
            $t->string('mensagem', 140)->nullable();
            $t->boolean('ignorado')->default(false);          // cliente dispensou avaliar
            $t->timestamps();
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE pedido_avaliacoes ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE pedido_avaliacoes FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON pedido_avaliacoes');
        DB::statement(
            "CREATE POLICY tenant_isolation ON pedido_avaliacoes
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
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON pedido_avaliacoes');
        }
        Schema::dropIfExists('pedido_avaliacoes');
    }
};
