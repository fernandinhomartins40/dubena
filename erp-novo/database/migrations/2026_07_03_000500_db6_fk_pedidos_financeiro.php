<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DB-6 (auditoria) — formaliza a FK `pedidos.financeiro_id → financeiros`.
 *
 * A coluna nasceu como "FK formal quando o Financeiro (N5) chegar" (migration
 * 0005). O N5 chegou: agora existe a integridade referencial. nullOnDelete porque
 * um pedido pode não ter financeiro (rascunho/pendente) e cancelar o título não
 * deve apagar o pedido — só zerar o vínculo.
 *
 * Idempotente (só cria a FK se ainda não existir). NO-OP fora do pgsql (sqlite de
 * teste já valida a lógica sem FK nomeada).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql' || ! Schema::hasColumn('pedidos', 'financeiro_id')) {
            return;
        }

        $existe = DB::selectOne(
            "SELECT 1 AS x FROM information_schema.table_constraints
             WHERE table_name = 'pedidos' AND constraint_name = 'pedidos_financeiro_id_foreign'",
        );
        if ($existe) {
            return;
        }

        // Higieniza vínculos órfãos antes de criar a FK (aponta p/ financeiro inexistente).
        DB::statement(
            'UPDATE pedidos SET financeiro_id = NULL
             WHERE financeiro_id IS NOT NULL
               AND financeiro_id NOT IN (SELECT id FROM financeiros)',
        );

        Schema::table('pedidos', function (Blueprint $t) {
            $t->foreign('financeiro_id')->references('id')->on('financeiros')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('pedidos', function (Blueprint $t) {
            $t->dropForeign('pedidos_financeiro_id_foreign');
        });
    }
};
