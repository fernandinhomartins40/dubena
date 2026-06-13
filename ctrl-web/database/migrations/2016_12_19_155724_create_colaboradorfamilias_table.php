<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateColaboradorfamiliasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('colaboradorfamilias', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('colaborador_id');
          $table->unsignedInteger('parentesco_id');
          $table->string('nome', 100);
          $table->date('datanascimento')->nullable()->default(null);
          $table->boolean('ativo')->default(true);

          $table->timestamps();
          $table->foreign('empresa_id')->references('id')->on('empresas');
          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('colaborador_id')->references('id')->on('colaboradors')->onDelete('cascade');
          $table->foreign('parentesco_id')->references('id')->on('parentescos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('colaboradorfamilias');
    }
}
