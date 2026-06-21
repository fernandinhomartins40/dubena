<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N4 — Pedidos / Vendas.
 *
 * Substitui a matriz IMPLÍCITA (situação×operação×setor) do legado por uma MÁQUINA
 * DE ESTADOS EXPLÍCITA: cada situação declara seu `efeito` (PENDENTE/CONCLUIDO/
 * CANCELADO). O PedidoService decide a movimentação (SAÍDA/ENTRADA estoque, gerar/
 * estornar financeiro) a partir da TRANSIÇÃO de efeito — não de flags espalhadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Operação do pedido (canal/natureza): disk, pdv, venda direta, convênio...
        Schema::create('pedidooperacoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->string('descricao');
            $t->boolean('convenio')->default(false);
            $t->boolean('gasbolso')->default(false);
            $t->boolean('disk')->default(false);
            $t->boolean('venda_direta')->default(false);
            $t->boolean('pdv')->default(false);
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->unique(['grupo_id', 'descricao']);
        });

        // Situação do pedido com EFEITO explícito na máquina de estados.
        Schema::create('pedidosituacoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->string('descricao');
            // efeito: PENDENTE (em aberto), CONCLUIDO (venda concretizada → SAÍDA estoque
            // + gera financeiro), CANCELADO (revertida → ENTRADA estoque + estorna).
            $t->string('efeito', 20)->default('PENDENTE');
            $t->boolean('padrao_tela_pedido')->default(false);
            $t->integer('ordem')->default(0); // posição no kanban
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->unique(['grupo_id', 'descricao']);
        });

        Schema::create('pedidos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $t->foreignId('pedidooperacao_id')->nullable()->constrained('pedidooperacoes')->nullOnDelete();
            $t->foreignId('pedidosituacao_id')->constrained('pedidosituacoes')->restrictOnDelete();
            $t->foreignId('setor_id')->nullable()->constrained('setores')->nullOnDelete(); // setor que baixa estoque
            $t->foreignId('atendente_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('entregador_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('financeiro_id')->nullable(); // FK formal quando Financeiro chegar (N5)

            $t->dateTime('datahora')->nullable();
            $t->dateTime('datahora_acao')->nullable();

            // Entrega
            $t->boolean('entrega_urgente')->default(false);
            $t->string('entrega_telefone', 30)->nullable();
            $t->decimal('entrega_taxa', 12, 2)->default(0);
            $t->decimal('entrega_troco_para', 12, 2)->nullable();

            // Valores (decimal nativo)
            $t->decimal('valor_venda', 12, 2)->default(0);
            $t->decimal('valor_desconto', 12, 2)->default(0);

            $t->text('observacao')->nullable();
            $t->boolean('estoque_movimentado')->default(false); // idempotência da baixa
            $t->timestamps();
            $t->index(['empresa_id', 'pedidosituacao_id']);
            $t->index(['empresa_id', 'datahora']);
        });

        // Itens do pedido (DTO nomeado, valores crus).
        Schema::create('pedidoitens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $t->foreignId('produto_id')->constrained('produtos')->restrictOnDelete();
            $t->decimal('quantidade', 14, 3);
            $t->decimal('preco_unitario', 12, 2);
            $t->decimal('desconto', 12, 2)->default(0);
            $t->decimal('valor_total', 12, 2);
            $t->timestamps();
        });

        // Histórico de mudança de situação (auditoria da máquina de estados).
        Schema::create('pedidosituacaohistorico', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $t->foreignId('pedidosituacao_id')->constrained('pedidosituacoes')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('efeito', 20);
            $t->dateTime('datahora');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidosituacaohistorico');
        Schema::dropIfExists('pedidoitens');
        Schema::dropIfExists('pedidos');
        Schema::dropIfExists('pedidosituacoes');
        Schema::dropIfExists('pedidooperacoes');
    }
};
