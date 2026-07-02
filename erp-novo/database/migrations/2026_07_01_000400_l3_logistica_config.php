<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * L3 — Configuração da distribuição por empresa. Modo (`sugerir` | `auto`), pesos
 * do ranking, raio máximo e teto de carga. 1 linha por empresa (upsert). Começa em
 * `sugerir` (decisão de produto): o operador confirma; migra para `auto` quando
 * houver confiança nos dados. Tenant-scoped → RLS inline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistica_configs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->string('modo', 12)->default('sugerir'); // 'sugerir' | 'auto'
            $t->decimal('peso_distancia', 4, 2)->default(0.70);
            $t->decimal('peso_carga', 4, 2)->default(0.30);
            $t->unsignedInteger('raio_maximo_km')->nullable(); // null = sem limite
            $t->unsignedInteger('teto_carga')->nullable();      // null = sem teto
            $t->timestamps();
            $t->unique('empresa_id');
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE logistica_configs ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE logistica_configs FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON logistica_configs');
        DB::statement(
            "CREATE POLICY tenant_isolation ON logistica_configs
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
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON logistica_configs');
        }
        Schema::dropIfExists('logistica_configs');
    }
};
