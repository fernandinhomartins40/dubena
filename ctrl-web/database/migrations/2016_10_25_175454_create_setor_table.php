<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSetorTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::create('setors', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('cidade_id');
          $table->unsignedInteger('bairro_id');
          $table->unsignedInteger('monitoramentocerca_id')->nullable()->default(null);
          $table->string('descricao');
          $table->string('endereco');
          $table->string('numero');
          $table->string('complemento');
          $table->string('cep');
          $table->float('latitude');
          $table->float('longitude');
          $table->boolean('estoqueproprio')->default(false);
          $table->boolean('ativo')->default(true);
          $table->timestamps();

          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id')->references('id')->on('empresas');
          $table->foreign('cidade_id')->references('id')->on('cidades');
          $table->foreign('bairro_id')->references('id')->on('bairros');
      });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
