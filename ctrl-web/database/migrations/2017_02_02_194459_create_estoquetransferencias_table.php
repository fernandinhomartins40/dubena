<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEstoquetransferenciasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('estoquetransferencias', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('user_id');
          $table->unsignedInteger('origemsetor_id');
          $table->unsignedInteger('destinosetor_id');
          $table->dateTime('datahora');
          $table->dateTime('datahoracompetencia');
          $table->string('observacoes', 500);

          $table->timestamps();

          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id')->references('id')->on('empresas');
          $table->foreign('user_id')->references('id')->on('users');
          $table->foreign('origemsetor_id')->references('id')->on('setors');
          $table->foreign('destinosetor_id')->references('id')->on('setors');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('estoquetransferencias');
    }
}
