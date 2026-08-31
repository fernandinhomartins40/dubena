<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F2-04 — separa plano VENDÁVEL de plano TRANSITÓRIO.
 *
 * `Legacy Full` existe para uma finalidade só: conservar o acesso de quem já
 * opera enquanto o fail-open é removido. Ele não é uma oferta.
 *
 * Sem esta distinção, `Legacy Full` apareceria na grade comercial ao lado de
 * Essencial e Completo, e nada impediria atribuí-lo a uma revenda nova — que é
 * justamente como "transição" vira "plano gratuito com tudo incluso" e a
 * remoção do fail-open deixa de acontecer.
 *
 * `ativo` não serve para isso: o plano transitório PRECISA estar ativo, senão
 * as assinaturas que apontam para ele deixam de valer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            // Default false: todo plano é vendável até que se diga o contrário.
            // O inverso faria um plano novo nascer invisível na grade.
            $table->boolean('transitorio')->default(false)->after('ativo');
        });
    }

    public function down(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->dropColumn('transitorio');
        });
    }
};
