<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePedidooperacaosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pedidooperacaos', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->string('descricao');
          $table->boolean('ativo')->default(true);

          $table->boolean('convenio')->default(false);
          $table->boolean('gasbolso')->default(false);
          $table->boolean('disk')->default(false);
          $table->boolean('vendadireta')->default(false);
          $table->boolean('pdv')->default(false);

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
        Schema::drop('pedidooperacaos');
    }
}
