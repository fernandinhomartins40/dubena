<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePedidoAvaliacaosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sgcm_api')->create('pedidoavaliacoes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger("pedido_id");
            $table->foreign("pedido_id")->references("id")->on("pedidos");
            $table->string("mensagem");
            $table->float("rating", 3, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sgcm_api')->dropIfExists('pedidoavaliacoes');
    }
}
