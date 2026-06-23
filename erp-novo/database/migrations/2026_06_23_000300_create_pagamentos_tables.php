<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C4 resto — meios de pagamento que faltavam do legado:
 *  - cartao_transacoes: pagamento por cartão (bandeira/NSU/parcelas/taxa) com
 *    crédito no caixa (líquido) e controle de antecipação.
 *  - gasdopovo_beneficios: programa "Gás do Povo" (auxílio gov.) — registro do
 *    benefício por cliente, com saque/baixa contra um pedido.
 *
 * Cheque (troco/encontro-de-contas) reusa a tabela `cheques` existente + service.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cartao_transacoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $t->foreignId('conta_id')->nullable()->constrained('contas')->nullOnDelete();
            $t->string('bandeira', 30)->nullable();          // visa/master/elo/...
            $t->string('tipo', 10)->default('credito');      // credito/debito
            $t->string('nsu', 30)->nullable();               // identificador da operadora
            $t->string('autorizacao', 30)->nullable();
            $t->unsignedTinyInteger('parcelas')->default(1);
            $t->decimal('valor_bruto', 14, 2)->default(0);
            $t->decimal('taxa_percentual', 6, 2)->default(0);
            $t->decimal('valor_liquido', 14, 2)->default(0);
            $t->string('situacao', 20)->default('confirmada'); // confirmada/cancelada
            $t->timestamps();
            $t->index(['empresa_id', 'nsu']);
        });

        Schema::create('gasdopovo_beneficios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $t->string('nis', 20)->nullable();               // identificação social do beneficiário
            $t->string('competencia', 7);                    // YYYY-MM
            $t->decimal('valor', 14, 2)->default(0);
            $t->string('situacao', 20)->default('disponivel'); // disponivel/utilizado/cancelado
            $t->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $t->timestamps();
            $t->index(['empresa_id', 'situacao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gasdopovo_beneficios');
        Schema::dropIfExists('cartao_transacoes');
    }
};
