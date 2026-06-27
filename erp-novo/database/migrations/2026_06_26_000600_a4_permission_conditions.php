<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * A4 — ABAC: condições de atributo nas permissões.
 *
 * Uma condição limita QUANDO/SOB QUAIS regras um papel exerce uma permissão:
 *  - limite:    valor do recurso ≤ teto (ex.: aprovar compra até R$ X);
 *  - ownership: o recurso pertence ao próprio usuário (ex.: estornar só o caixa
 *               que ele abriu);
 *  - horario:   ação só dentro de uma janela de horário.
 *
 * A condição é amarrada a (papel, permissão) e escopada por empresa → entra na
 * RLS (aplicada aqui, pois a ..._000300 já rodou). `parametros` é JSON livre por
 * tipo (ex.: {"valor_max": 5000}, {"campo_dono":"operador_id"}, {"de":"08:00","ate":"18:00"}).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->string('tipo'); // 'limite' | 'ownership' | 'horario'
            $table->json('parametros')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->index(['empresa_id', 'role_id', 'permission_id']);
        });

        $this->aplicarRls('permission_conditions');
    }

    public function down(): void
    {
        $this->removerRls('permission_conditions');
        Schema::dropIfExists('permission_conditions');
    }

    private function aplicarRls(string $tabela): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

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

    private function removerRls(string $tabela): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
        DB::statement("ALTER TABLE {$tabela} NO FORCE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$tabela} DISABLE ROW LEVEL SECURITY");
    }
};
