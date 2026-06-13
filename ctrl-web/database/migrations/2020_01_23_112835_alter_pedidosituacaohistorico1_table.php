<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterPedidosituacaohistorico1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pedidosituacaohistoricos', function (Blueprint $table) {
            $table->unsignedInteger("apipedido_id")->nullable()->default(null);
            $table->boolean("enviadoapi")->nullable()->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pedidosituacaohistoricos', function (Blueprint $table) {
            $table->dropColumn("apipedido_id");
            $table->dropColumn("enviadoapi");
        });
    }
}
