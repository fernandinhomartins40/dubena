<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C4 — Condição de pagamento + vínculo no pedido.
 *
 * A auditoria mostrou que `gerarDoPedido` ignorava a condição e gerava sempre 1
 * parcela. Aqui a condição passa a existir como entidade (nº de parcelas +
 * intervalo + dias da 1ª) e o pedido referencia uma; o FinanceiroService usa isso
 * para parcelar de verdade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('condicaopagamentos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->string('descricao');
            $t->unsignedSmallInteger('num_parcelas')->default(1);
            $t->unsignedSmallInteger('intervalo_dias')->default(30); // entre parcelas
            $t->unsignedSmallInteger('dias_primeira')->default(0);    // até a 1ª parcela
            $t->boolean('a_vista')->default(true);
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->unique(['grupo_id', 'descricao']);
        });

        Schema::table('pedidos', function (Blueprint $t) {
            $t->foreignId('condicaopagamento_id')->nullable()->after('pedidosituacao_id')
                ->constrained('condicaopagamentos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $t) {
            $t->dropConstrainedForeignId('condicaopagamento_id');
        });
        Schema::dropIfExists('condicaopagamentos');
    }
};
