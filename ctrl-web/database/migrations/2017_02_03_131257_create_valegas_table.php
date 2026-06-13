<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateValegasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('valegas', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('valegassituacao_id');
          $table->unsignedInteger('cliente_id');
          $table->unsignedInteger('produto_id');
          $table->unsignedInteger('valegasvenda_id');
          $table->unsignedInteger('pedido_id')->nullable()->default(null);
          $table->string('codigo', 9);
          $table->date('datagerecao');
          $table->date('databaixa');
          $table->unsignedInteger('prevendasequencia');

          $table->timestamps();

          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id')->references('id')->on('empresas');
          $table->foreign('valegassituacao_id')->references('id')->on('valegassituacaos');
          $table->foreign('cliente_id')->references('id')->on('clientes');
          $table->foreign('produto_id')->references('id')->on('produtos');
          $table->foreign('valegasvenda_id')->references('id')->on('valegasvendas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('valegas');
    }
}
