<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cor da coluna do Kanban de pedidos (F18.K). Hex curto (#RRGGBB) usado só na
 * UI; opcional — colunas sem cor caem no estilo neutro padrão.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidosituacoes', function (Blueprint $t) {
            $t->string('cor', 7)->nullable()->after('efeito');
        });
    }

    public function down(): void
    {
        Schema::table('pedidosituacoes', function (Blueprint $t) {
            $t->dropColumn('cor');
        });
    }
};
