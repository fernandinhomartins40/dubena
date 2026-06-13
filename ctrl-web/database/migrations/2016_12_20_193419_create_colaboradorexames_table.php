<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateColaboradorexamesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('colaboradorexames', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('colaborador_id');
          $table->unsignedInteger('tipoexames_id');
          $table->date('data')->nullable()->default(null);
          $table->date('datavencimento')->nullable()->default(null);
          $table->boolean('alerta')->default(false);

          $table->timestamps();
          $table->foreign('empresa_id')->references('id')->on('empresas');
          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('colaborador_id')->references('id')->on('colaboradors')->onDelete('cascade');
          $table->foreign('tipoexames_id')->references('id')->on('tipoexames');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('colaboradorexames');
    }
}
