<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N8 — módulos satélite: convênio (fechamento mensal), vale-gás (cupom pré-pago),
 * comodato (vasilhame). Todos se apoiam em Financeiro (N5) / Estoque (N3).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Convênio: vínculo de um cliente conveniado (empresa pagadora) com regras.
        Schema::create('convenios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete(); // o conveniado pagador
            $t->string('descricao');
            $t->unsignedSmallInteger('dia_fechamento')->default(1);
            $t->unsignedSmallInteger('dia_vencimento')->default(10);
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->index(['empresa_id', 'ativo']);
        });

        // Fechamento de convênio: consolida pedidos do período num financeiro.
        Schema::create('convenio_fechamentos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('convenio_id')->constrained('convenios')->cascadeOnDelete();
            $t->foreignId('financeiro_id')->nullable()->constrained('financeiros')->nullOnDelete();
            $t->date('periodo_inicio');
            $t->date('periodo_fim');
            $t->decimal('valor_total', 12, 2)->default(0);
            $t->unsignedInteger('total_pedidos')->default(0);
            $t->string('situacao', 20)->default('ABERTO'); // ABERTO, FECHADO, FATURADO
            $t->timestamps();
            $t->index(['convenio_id', 'periodo_inicio']);
        });

        // Liga pedidos a um fechamento de convênio (1 pedido entra em 1 fechamento).
        Schema::create('convenio_fechamento_pedidos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('convenio_fechamento_id')->constrained('convenio_fechamentos')->cascadeOnDelete();
            $t->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $t->decimal('valor', 12, 2);
            $t->timestamps();
            $t->unique('pedido_id'); // um pedido só em um fechamento
        });

        // Vale-gás: cupom pré-pago.
        Schema::create('vale_gas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $t->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete();
            $t->foreignId('financeiro_id')->nullable()->constrained('financeiros')->nullOnDelete();
            $t->string('codigo', 40)->unique();
            $t->decimal('valor', 12, 2);
            $t->date('validade')->nullable();
            // EMITIDO, PAGO, UTILIZADO, CANCELADO, EXPIRADO.
            $t->string('situacao', 20)->default('EMITIDO');
            $t->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete(); // onde foi usado
            $t->dateTime('utilizado_em')->nullable();
            $t->timestamps();
            $t->index(['empresa_id', 'situacao']);
        });

        // Comodato: vasilhame emprestado ao cliente (controla retorno).
        Schema::create('comodatos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $t->foreignId('produto_id')->constrained('produtos')->restrictOnDelete(); // o vasilhame
            $t->foreignId('setor_id')->nullable()->constrained('setores')->nullOnDelete();
            $t->decimal('quantidade', 14, 3);
            $t->decimal('quantidade_devolvida', 14, 3)->default(0);
            // ATIVO (emprestado), DEVOLVIDO, PARCIAL, CANCELADO.
            $t->string('situacao', 20)->default('ATIVO');
            $t->date('data_emprestimo');
            $t->date('data_devolucao')->nullable();
            $t->timestamps();
            $t->index(['empresa_id', 'situacao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comodatos');
        Schema::dropIfExists('vale_gas');
        Schema::dropIfExists('convenio_fechamento_pedidos');
        Schema::dropIfExists('convenio_fechamentos');
        Schema::dropIfExists('convenios');
    }
};
