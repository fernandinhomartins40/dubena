<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateConveniofechamentopedidosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('conveniofechamentopedidos', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('conveniofechamento_id');
          $table->unsignedInteger('pedido_id');
          $table->unsignedInteger('cliente_id');
          $table->date('pedidodata');
          $table->date('pedidovalor', 15, 4);

          $table->timestamps();

          $table->foreign('conveniofechamento_id')->references('id')->on('conveniofechamentos')->onDelete('cascade');
          $table->foreign('pedido_id')->references('id')->on('pedidos');
          $table->foreign('cliente_id')->references('id')->on('clientes');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('conveniofechamentopedidos');
    }
}
