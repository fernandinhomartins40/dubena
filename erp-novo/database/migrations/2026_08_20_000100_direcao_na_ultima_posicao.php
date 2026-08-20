<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Direção (azimute) na última posição.
 *
 * `monitora_posicoes` já guarda a direção de cada ponto, mas a tabela de última
 * posição — que é a que o mapa ao vivo lê — não. Sem ela o ícone do veículo não
 * pode apontar para onde ele está indo, e um caminhão parado fica igual a um
 * caminhão descendo a avenida.
 *
 * O legado guardava o mesmo dado como `azimute` em `ultimaposicaos`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitora_ultima_posicao', function (Blueprint $t) {
            $t->unsignedSmallInteger('direcao')->nullable()->after('velocidade');
        });
    }

    public function down(): void
    {
        Schema::table('monitora_ultima_posicao', function (Blueprint $t) {
            $t->dropColumn('direcao');
        });
    }
};
