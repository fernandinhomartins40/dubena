<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterPedidosituacaosTable3 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pedidosituacaos', function (Blueprint $table) {
            $table->boolean('pedidorecebidomovel')->default(false);
            $table->boolean('pedidolidomovel')->default(false);
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
