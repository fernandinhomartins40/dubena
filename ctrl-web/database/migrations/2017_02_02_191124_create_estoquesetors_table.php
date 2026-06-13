<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEstoquesetorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('estoquesetors', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('produto_id');
          $table->unsignedInteger('setor_id');
          $table->decimal('quantidade', 15, 4);
          $table->decimal('quantidademinima', 15, 4);
          $table->decimal('quantidademaxima', 15, 4);

          $table->timestamps();

          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id')->references('id')->on('empresas');
          $table->foreign('produto_id')->references('id')->on('produtos');
          $table->foreign('setor_id')->references('id')->on('setors');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('estoquesetors');
    }
}
