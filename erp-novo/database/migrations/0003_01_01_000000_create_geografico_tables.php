<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N2 — Geográfico (base do endereço do cliente): cidades, bairros, ruas.
 * Escopo por GRUPO (compartilhado entre empresas do grupo, como no legado).
 * Bairro e Rua pertencem a uma Cidade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cidades', function (Blueprint $t) {
            $t->id();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->string('descricao');
            $t->string('uf', 2);
            $t->unsignedInteger('cod_ibge')->nullable();
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->foreign('uf')->references('uf')->on('estados');
            $t->index(['grupo_id', 'uf']);
            $t->unique(['grupo_id', 'descricao', 'uf']);
        });

        Schema::create('bairros', function (Blueprint $t) {
            $t->id();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->foreignId('cidade_id')->constrained('cidades')->cascadeOnDelete();
            $t->string('descricao');
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->unique(['cidade_id', 'descricao']);
        });

        Schema::create('ruas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->foreignId('cidade_id')->constrained('cidades')->cascadeOnDelete();
            $t->string('descricao');
            $t->string('cep', 10)->nullable();
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->index(['cidade_id', 'descricao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruas');
        Schema::dropIfExists('bairros');
        Schema::dropIfExists('cidades');
    }
};
