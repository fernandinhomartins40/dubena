<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMetavendasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('metavendas', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('produto_id');
          $table->unsignedInteger('setor_id');

          $table->date('datameta');
          $table->decimal('valormeta', 15, 4);
          $table->decimal('valordesafio', 15, 4);
          $table->decimal('valorperfil', 15, 4);
          $table->decimal('quantidade', 15, 4);
          $table->decimal('quantidadedesafio', 15, 4);
          $table->decimal('quantidadeperfil', 15, 4);
          $table->text('causa');
          $table->text('acao');

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
        Schema::drop('metavendas');
    }
}
