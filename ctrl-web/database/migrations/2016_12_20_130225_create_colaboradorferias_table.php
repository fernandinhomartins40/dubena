<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateColaboradorferiasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('colaboradorferias', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('colaborador_id');
          $table->date('datainicio')->nullable()->default(null);
          $table->unsignedInteger('dias');
          $table->boolean('gozada')->default(false);

          $table->timestamps();
          $table->foreign('empresa_id')->references('id')->on('empresas');
          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('colaborador_id')->references('id')->on('colaboradors')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('colaboradorferias');
    }
}
