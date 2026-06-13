<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterPedidos3Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn('entregapontoreferencia');
        });
        Schema::table('pedidos', function (Blueprint $table) {
            $table->text('entregapontoreferencia', 100)->nullable();
        });
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn('financeiro_id');
        });
        Schema::table('pedidos', function (Blueprint $table) {
            $table->unsignedInteger('financeiro_id')->nullable();
            $table->foreign('financeiro_id')->references('id')->on('financeiros')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pedidos', function (Blueprint $table) {
            //
        });
    }
}
