<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PIE P2-E-11: ajuste de ponto de dia fechado passa a exigir motivo.
 *
 * O motivo fica NA LINHA (e não só na trilha) porque é ele que o RH precisa ver
 * ao lado das horas quando a folha for conferida; a trilha (`audit_logs`, o
 * model é auditável) guarda quem mudou e o antes/depois. Coluna nova e
 * anulável: aditiva, sem efeito no rollback de nenhuma feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('colaborador_pontos', 'motivo_ajuste')) {
            return;
        }
        Schema::table('colaborador_pontos', function (Blueprint $t) {
            $t->string('motivo_ajuste', 255)->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('colaborador_pontos', 'motivo_ajuste')) {
            Schema::table('colaborador_pontos', fn (Blueprint $t) => $t->dropColumn('motivo_ajuste'));
        }
    }
};
