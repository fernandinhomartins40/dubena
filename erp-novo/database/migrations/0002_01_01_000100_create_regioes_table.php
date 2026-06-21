<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N1 — Regiões (área de atendimento/entrega), escopadas por GRUPO.
 * Legado: regioes (descricao + ativo). Cadastro de apoio com semântica própria,
 * por isso tem tabela dedicada (referenciada por clientes/setores nas fases N2+).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regioes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->string('descricao');
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->unique(['grupo_id', 'descricao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regioes');
    }
};
