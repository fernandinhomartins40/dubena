<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProdutoclassesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('produtoclasses', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->string('descricao', 100);
          $table->boolean('entrada')->default(false);
          $table->boolean('saida')->default(false);
          $table->boolean('materia_prima')->default(false);
          $table->boolean('produto_acabado')->default(false);
          $table->boolean('produto_processo')->default(false);
          $table->boolean('ativo')->default(true);

          $table->timestamps();
          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('produtoclasses');
    }
}
