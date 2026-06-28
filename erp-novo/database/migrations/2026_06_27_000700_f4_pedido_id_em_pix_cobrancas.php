<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F4 — PIX por PEDIDO (app). A cobrança PIX do legado nascia de uma parcela
 * financeira; o app gera PIX direto do pedido (checkout). Adiciona o vínculo
 * opcional `pedido_id` em pix_cobrancas (financeiroparcela_id segue nullable),
 * permitindo cobrar tanto via financeiro quanto via pedido do app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pix_cobrancas', function (Blueprint $t) {
            $t->foreignId('pedido_id')->nullable()->after('financeiroparcela_id')
                ->constrained('pedidos')->nullOnDelete();
            $t->index('pedido_id');
        });
    }

    public function down(): void
    {
        Schema::table('pix_cobrancas', function (Blueprint $t) {
            $t->dropConstrainedForeignId('pedido_id');
        });
    }
};
