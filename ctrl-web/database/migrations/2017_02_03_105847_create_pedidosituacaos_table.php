<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePedidosituacaosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pedidosituacaos', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->string('descricao');
          $table->boolean('padraotelapedido')->default(false);
          $table->boolean('entregafinalizada')->default(false);
          $table->boolean('entregacancelada')->default(false);
          $table->boolean('entregapendente')->default(false);
          $table->boolean('androidusa')->default(false);
          $table->boolean('fechadoconcluido')->default(false);//Só altera com senha mestre
          $table->boolean('fechadocancelado')->default(false);//Só altera com senha mestre
          $table->boolean('ativo')->default(true);

          $table->timestamps();

          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id')->references('id')->on('empresas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('pedidosituacaos');
    }
}
