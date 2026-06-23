<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C11 — operações de estoque que faltavam (a SPA já chamava):
 *  - requisições (pedido interno de material entre setores);
 *  - inventário / estoque físico (contagem → efetivação ajusta o saldo).
 *
 * A efetivação usa o EstoqueService (acerto/transferência) para manter o saldo
 * auditável (Σ histórico = saldo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estoque_requisicoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('setor_origem_id')->nullable()->constrained('setores')->nullOnDelete();
            $t->foreignId('setor_destino_id')->constrained('setores')->cascadeOnDelete();
            $t->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $t->decimal('quantidade', 14, 4);
            $t->string('situacao', 20)->default('pendente'); // pendente/atendida/cancelada
            $t->string('observacao')->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['empresa_id', 'situacao']);
        });

        // Inventário (cabeçalho da contagem) + itens (contado por produto/setor).
        Schema::create('estoque_inventarios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('setor_id')->constrained('setores')->cascadeOnDelete();
            $t->date('data');
            $t->string('situacao', 20)->default('aberto'); // aberto/efetivado
            $t->string('observacao')->nullable();
            $t->timestamps();
        });

        Schema::create('estoque_inventario_itens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('estoque_inventario_id')->constrained('estoque_inventarios')->cascadeOnDelete();
            $t->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $t->decimal('quantidade_contada', 14, 4);
            $t->decimal('quantidade_sistema', 14, 4)->nullable(); // saldo no momento da efetivação
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estoque_inventario_itens');
        Schema::dropIfExists('estoque_inventarios');
        Schema::dropIfExists('estoque_requisicoes');
    }
};
