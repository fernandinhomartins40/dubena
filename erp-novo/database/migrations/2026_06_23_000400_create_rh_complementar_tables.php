<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C5 resto — RH complementar do legado:
 *  - colaborador_exames: exames ocupacionais (ASO) com tipo, datas e vencimento.
 *  - colaborador_turnos: escala/turno de trabalho (entrada/saída por dia).
 *  - colaborador_pontos: registro de ponto (entrada/saída efetivas).
 *
 * Férias/afastamentos já são cobertos por colaborador_recessos (campo `tipo`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colaborador_exames', function (Blueprint $t) {
            $t->id();
            $t->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
            $t->string('tipo', 30)->default('periodico'); // admissional/periodico/demissional/retorno
            $t->date('realizado_em');
            $t->date('vencimento')->nullable();
            $t->string('resultado', 20)->default('apto');  // apto/inapto/apto-com-restricao
            $t->string('medico')->nullable();
            $t->string('observacao')->nullable();
            $t->timestamps();
            $t->index(['colaborador_id', 'vencimento']);
        });

        Schema::create('colaborador_turnos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
            $t->unsignedTinyInteger('dia_semana'); // 0=dom .. 6=sáb
            $t->time('entrada');
            $t->time('saida');
            $t->timestamps();
            $t->unique(['colaborador_id', 'dia_semana']);
        });

        Schema::create('colaborador_pontos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
            $t->date('data');
            $t->time('entrada')->nullable();
            $t->time('saida')->nullable();
            $t->timestamps();
            $t->index(['colaborador_id', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colaborador_pontos');
        Schema::dropIfExists('colaborador_turnos');
        Schema::dropIfExists('colaborador_exames');
    }
};
