<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePedidoItensTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pedidoitens', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pedido_id')->index();
            $table->unsignedInteger('produto_id')->index();

            $table->decimal('quantidade', 15, 4);
            $table->decimal('precovendaunitario', 15, 4);
            $table->decimal('precovendatotal', 15, 4);
            $table->timestamps();

            $table->foreign('pedido_id')->references('id')->on('pedidos');
            $table->foreign('produto_id')->references('id')->on('produtoimportacoes');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pedidoitens');
    }
}
