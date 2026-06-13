<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterPedidosituacaos2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pedidosituacaos', function (Blueprint $table) {
            $table->boolean('entregatranferida')->default('0');
            $table->boolean('ementrega')->default('0');
            $table->boolean('entregadoroffline')->default('0');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pedidosituacaos', function (Blueprint $table) {
            //
        });
    }
}
