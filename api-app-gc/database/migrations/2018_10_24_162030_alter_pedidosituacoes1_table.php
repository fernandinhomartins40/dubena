<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterPedidosituacoes1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pedidosituacoes', function (Blueprint $table) {
//            $table->dropColumn("enviadoentregador");
            $table->string("info", 150)->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pedidosituacoes', function (Blueprint $table) {
//            $table->boolean("enviadoentregador")->default(false);
            $table->dropColumn("info");
        });
    }
}
