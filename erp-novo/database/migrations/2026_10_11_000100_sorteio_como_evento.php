<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O sorteio vira evento com registro: o que se sorteia, quando, quem conduziu
 * e QUAL número venceu.
 *
 * Antes só ficava `numero_sorteado` (texto). Sem o vínculo com a linha
 * vencedora não dava para mostrar o ganhador de novo — e a reapresentação para
 * gravar o vídeo depois precisa dele. Sem `sorteado_em`/`sorteado_por`, um
 * sorteio contestado pelo cliente não tinha como ser explicado.
 *
 * Aditiva: colunas novas e nulas, nada é reescrito. A policy de `sorteios` é
 * por linha e não lista colunas — continua valendo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sorteios', function (Blueprint $t) {
            $t->string('premio', 255)->nullable()->after('descricao');
            $t->timestamp('sorteado_em')->nullable()->after('numero_sorteado');
            $t->foreignId('sorteado_por')->nullable()->after('sorteado_em')->constrained('users')->nullOnDelete();
            $t->foreignId('ganhador_numero_id')->nullable()->after('sorteado_por')->constrained('sorteio_numeros')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sorteios', function (Blueprint $t) {
            $t->dropConstrainedForeignId('ganhador_numero_id');
            $t->dropConstrainedForeignId('sorteado_por');
            $t->dropColumn(['premio', 'sorteado_em']);
        });
    }
};
