<?php

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F0 — Isola `monitora_cerca_pontos` por tenant.
 *
 * A tabela nasceu (2026_06_26_000200_cercas_poligono) só com cerca_id/lat/lng/ordem,
 * SEM empresa_id/grupo_id. Como a RLS é por auto-descoberta de coluna
 * (2026_06_26_000300_rls_tenant_completa), a tabela ficava FORA do isolamento: a 2ª
 * barreira (RLS no banco) não a cobria e o model não tinha BelongsToTenant. Vértices de
 * geofence de todas as empresas conviviam numa tabela sem policy.
 *
 * Aqui: adiciona empresa_id/grupo_id (preenchidos a partir da cerca-mãe), e aplica a
 * MESMA policy de RLS das demais tabelas (NO-OP fora do PostgreSQL — sqlite em teste).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitora_cerca_pontos', function (Blueprint $t) {
            $t->foreignId('empresa_id')->nullable()->after('cerca_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->nullable()->after('empresa_id');
        });

        // Backfill a partir da cerca-mãe (cercas já são isoladas por empresa).
        // Subquery correlacionada — portável entre sqlite e PostgreSQL (UPDATE..JOIN não é).
        DB::table('monitora_cerca_pontos')->update([
            'empresa_id' => DB::raw('(select empresa_id from monitora_cercas where monitora_cercas.id = monitora_cerca_pontos.cerca_id)'),
            'grupo_id' => DB::raw('(select grupo_id from monitora_cercas where monitora_cercas.id = monitora_cerca_pontos.cerca_id)'),
        ]);

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Mesma policy das demais tabelas tenant (espelha rls_tenant_completa).
        DB::statement('ALTER TABLE monitora_cerca_pontos ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE monitora_cerca_pontos FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON monitora_cerca_pontos');
        DB::statement(
            "CREATE POLICY tenant_isolation ON monitora_cerca_pontos
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
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON monitora_cerca_pontos');
            DB::statement('ALTER TABLE monitora_cerca_pontos NO FORCE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE monitora_cerca_pontos DISABLE ROW LEVEL SECURITY');
        }

        Schema::table('monitora_cerca_pontos', function (Blueprint $t) {
            $t->dropConstrainedForeignId('empresa_id');
            $t->dropColumn('grupo_id');
        });
    }
};
