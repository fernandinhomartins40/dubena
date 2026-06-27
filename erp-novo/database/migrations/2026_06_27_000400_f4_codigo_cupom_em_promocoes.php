<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F4 — Cupom no app: uma PROMOÇÃO com código vira um cupom resgatável (moderniza o
 * coupon code-based do legado app/Api sem criar uma 2ª entidade). O código é
 * opcional (promoção sem código = campanha automática) e único por grupo quando
 * preenchido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promocoes', function (Blueprint $t) {
            $t->string('codigo', 40)->nullable()->after('descricao');
            $t->index(['grupo_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::table('promocoes', function (Blueprint $t) {
            $t->dropIndex(['grupo_id', 'codigo']);
            $t->dropColumn('codigo');
        });
    }
};
