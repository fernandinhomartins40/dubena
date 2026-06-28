<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F3c — Preço por FORMA DE PAGAMENTO (paridade com o legado).
 *
 * O legado precifica por (produto × condição de pagamento): o mesmo produto custa
 * diferente em dinheiro × crédito (alimenta o `Payment.productPrices` do app). O
 * ERP-NOVO só tinha `produto.preco_venda` único. Esta tabela porta essa capacidade
 * sem reduzir funcionalidade.
 *
 * Escopo por empresa (como produto). Quando NÃO há linha para (produto, condição), a
 * cotação cai para `preco_venda`/`preco_gasdopovo` do produto (compatível com quem
 * ainda não cadastrou preços por condição). `gasdopovo` separa a tabela de preços GP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produto_condicao_precos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $t->foreignId('condicaopagamento_id')->constrained('condicaopagamentos')->cascadeOnDelete();
            $t->boolean('gasdopovo')->default(false);
            $t->decimal('valor', 12, 2);
            $t->timestamps();

            // Um preço por (produto, condição, gp) dentro da empresa.
            $t->unique(['empresa_id', 'produto_id', 'condicaopagamento_id', 'gasdopovo'], 'prod_cond_preco_unq');
            $t->index(['empresa_id', 'condicaopagamento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_condicao_precos');
    }
};
