<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateContatalaosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contatalaos', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('conta_id');
          $table->unsignedInteger('chequenuminicial');
          $table->unsignedInteger('chequenumfinal');
          $table->unsignedInteger('chequenumatual');

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
        Schema::drop('contatalaos');
    }
}
