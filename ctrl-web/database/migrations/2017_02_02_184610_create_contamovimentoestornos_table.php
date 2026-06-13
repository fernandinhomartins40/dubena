<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateContamovimentoestornosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contamovimentoestornos', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('contamovimento_id');
          $table->unsignedInteger('user_id');
          $table->string('motivo', 500);

          $table->timestamps();

          $table->foreign('contamovimento_id')->references('id')->on('contamovimentos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('contamovimentoestornos');
    }
}
