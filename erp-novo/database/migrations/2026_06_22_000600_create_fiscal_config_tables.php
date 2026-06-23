<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C12 — configuração fiscal: operações fiscais (natureza × CFOP × movimentação)
 * e malha fiscal genérica (tabelas de apoio: CFOP, CST, NCM, etc., por tipo).
 * Últimos endpoints da SPA que faltavam (fiscal/operacoes, fiscal/malha/{tipo}).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operacoes_fiscais', function (Blueprint $t) {
            $t->id();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->string('descricao');
            $t->string('descricao_fiscal')->nullable(); // natureza da operação (vai na NF)
            $t->string('cfop', 4)->nullable();
            $t->boolean('movimenta_estoque')->default(true);
            $t->boolean('movimenta_financeiro')->default(true);
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->unique(['grupo_id', 'descricao']);
        });

        Schema::create('malha_fiscal', function (Blueprint $t) {
            $t->id();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->string('tipo', 30);      // cfop / cst / ncm / cest / ...
            $t->string('codigo', 20)->nullable();
            $t->string('descricao');
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->index(['grupo_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('malha_fiscal');
        Schema::dropIfExists('operacoes_fiscais');
    }
};
