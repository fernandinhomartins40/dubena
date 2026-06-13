<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRecessotiposTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('recessotipos', function (Blueprint $table) {
          $table->increments('id');

          $table->unsignedInteger('grupo_id');
          $table->string('descricao', 100);
          $table->string('cor', 20);
          $table->string('legenda', 3);
          $table->boolean('ativo', 1);

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
        Schema::drop('recessotipos');
    }
}
