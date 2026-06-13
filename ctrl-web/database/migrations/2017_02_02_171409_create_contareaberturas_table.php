<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateContareaberturasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contareaberturas', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('conta_id');
          $table->unsignedInteger('user_id');
          $table->dateTime('datahorareaberta');
          $table->string('motivo', 500);

          $table->timestamps();

          $table->foreign('conta_id')->references('id')->on('contas')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('contareaberturas');
    }
}
