<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePromocaosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('promocaos', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('produto_id');
          $table->unsignedInteger('premioproduto_id');
          $table->string('descricao');
          $table->dateTime('datahorainicio');
          $table->dateTime('datahorafim');
          $table->unsignedInteger('quantidadepedidos');
          $table->unsignedInteger('quantidadepremios');
          $table->boolean('ativo')->default(true);

          $table->timestamps();

          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id')->references('id')->on('empresas');
          $table->foreign('produto_id')->references('id')->on('produtos');
          $table->foreign('premioproduto_id')->references('id')->on('produtos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('promocaos');
    }
}
