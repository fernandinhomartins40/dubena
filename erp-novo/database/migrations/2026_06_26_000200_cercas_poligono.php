<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cercas como POLÍGONO (paridade com o ctrl-web legado).
 *
 * O erp-novo nascera só com geofencing circular (centro+raio). O legado usa
 * polígono livre (N vértices). Aqui:
 *  - monitora_cercas ganha cor/setor_id/grupo_id e centro/raio viram nullable
 *    (compatibilidade: cerca pode ser polígono OU, no futuro, círculo).
 *  - nova monitora_cerca_pontos guarda os vértices (lat/lng) em ordem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitora_cercas', function (Blueprint $t) {
            $t->foreignId('grupo_id')->nullable()->after('empresa_id');
            $t->foreignId('setor_id')->nullable()->after('descricao')->constrained('setores')->nullOnDelete();
            $t->string('cor', 7)->nullable()->after('descricao');
            // centro/raio passam a ser opcionais (cerca polígono não os usa).
            $t->decimal('centro_lat', 10, 7)->nullable()->change();
            $t->decimal('centro_lng', 10, 7)->nullable()->change();
            $t->decimal('raio_metros', 10, 2)->nullable()->change();
        });

        Schema::create('monitora_cerca_pontos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cerca_id')->constrained('monitora_cercas')->cascadeOnDelete();
            $t->decimal('latitude', 10, 7);
            $t->decimal('longitude', 10, 7);
            $t->integer('ordem')->default(0); // sequência do vértice no polígono
            $t->timestamps();
            $t->index(['cerca_id', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitora_cerca_pontos');
        Schema::table('monitora_cercas', function (Blueprint $t) {
            $t->dropConstrainedForeignId('setor_id');
            $t->dropColumn(['grupo_id', 'cor']);
            // não revertemos o nullable de centro/raio (inofensivo).
        });
    }
};
