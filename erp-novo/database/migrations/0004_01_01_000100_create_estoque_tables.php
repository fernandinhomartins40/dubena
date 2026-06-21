<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N3 — Estoque com SALDO AUDITÁVEL (princípio #5 do plano).
 *
 * - setores: depósitos da empresa.
 * - estoquesaldos: saldo materializado por setor×produto (quantidade + min/max).
 * - estoquehistorico: TODO movimento (entrada/saída), com quantidade ASSINADA
 *   (+entrada, -saída) e o saldo resultante. Invariante de cutover/teste:
 *   Σ estoquehistorico.quantidade (por setor×produto) = estoquesaldos.quantidade.
 * - estoquefechamentos: saldo inicial/final por período (abertura/fechamento).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->string('descricao');
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->index(['empresa_id', 'descricao']);
        });

        Schema::create('estoquesaldos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('setor_id')->constrained('setores')->cascadeOnDelete();
            $t->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $t->decimal('quantidade', 14, 3)->default(0);
            $t->decimal('quantidade_minima', 14, 3)->nullable();
            $t->decimal('quantidade_maxima', 14, 3)->nullable();
            $t->decimal('custo_medio', 14, 4)->default(0);
            $t->timestamps();
            $t->unique(['setor_id', 'produto_id']);
        });

        Schema::create('estoquehistorico', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('setor_id')->constrained('setores')->cascadeOnDelete();
            $t->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $t->string('tipo', 20);                  // ENTRADA, SAIDA, TRANSFERENCIA, INVENTARIO, ACERTO
            $t->decimal('quantidade', 14, 3);        // ASSINADA: +entrada / -saída
            $t->decimal('custo_unitario', 14, 4)->nullable();
            $t->decimal('saldo_resultante', 14, 3); // saldo logo após este movimento
            $t->string('origem', 60)->nullable();    // ex.: 'pedido', 'transferencia', 'acerto'
            $t->unsignedBigInteger('origem_id')->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['setor_id', 'produto_id']);
            $t->index(['origem', 'origem_id']);
        });

        Schema::create('estoquefechamentos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('setor_id')->constrained('setores')->cascadeOnDelete();
            $t->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $t->date('data_fechamento');
            $t->decimal('saldo_inicial', 14, 3)->default(0);
            $t->decimal('saldo_final', 14, 3)->default(0);
            $t->boolean('aberto')->default(false);
            $t->timestamps();
            $t->index(['setor_id', 'produto_id', 'data_fechamento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estoquefechamentos');
        Schema::dropIfExists('estoquehistorico');
        Schema::dropIfExists('estoquesaldos');
        Schema::dropIfExists('setores');
    }
};
