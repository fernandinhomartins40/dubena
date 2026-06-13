<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePedidoitemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pedidoitems', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('pedido_id');
          $table->unsignedInteger('produto_id');

          $table->decimal('quantidade', 15, 4);
          $table->decimal('precovendaunitario', 15, 4);
          $table->decimal('precovendatotal', 15, 4);

          $table->timestamps();

          $table->foreign('pedido_id')->references('id')->on('pedidos')->onDelete('cascade');
          $table->foreign('produto_id')->references('id')->on('produtos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('pedidoitems');
    }
}
