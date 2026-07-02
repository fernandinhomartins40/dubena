<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L11 — Índices das consultas QUENTES da logística (rodam a cada poll/ping):
 *  - fila da Central e carga por entregador: pedidos (empresa, entregador, situação);
 *  - rota do entregador: mesmos campos;
 *  - histórico de atribuições por pedido já indexado na L1.
 * Idempotente e não destrutivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $t) {
            $t->index(['empresa_id', 'entregador_user_id', 'pedidosituacao_id'], 'pedidos_logistica_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $t) {
            $t->dropIndex('pedidos_logistica_idx');
        });
    }
};
