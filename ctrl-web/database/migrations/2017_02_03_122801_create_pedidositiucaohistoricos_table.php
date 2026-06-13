<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePedidositiucaohistoricosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pedidosituacaohistoricos', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('pedido_id');
          $table->unsignedInteger('pedidosituacao_id');
          $table->unsignedInteger('pedidomotivoatraso_id');
          $table->dateTime('datahora');

          $table->timestamps();

          $table->foreign('pedido_id')->references('id')->on('pedidos')->onDelete('cascade');
          $table->foreign('pedidosituacao_id', 'pedsithist_situacao_foreign')->references('id')->on('pedidosituacaos');
          $table->foreign('pedidomotivoatraso_id', 'pedsithist_mot_atr_foreign')->references('id')->on('pedidomotivoatrasos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('pedidositiucaohistoricos');
    }
}
