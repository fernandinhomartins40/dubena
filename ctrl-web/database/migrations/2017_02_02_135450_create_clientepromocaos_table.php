<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateClientepromocaosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('clientepromocaos', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('cliente_id');
          $table->dateTime('datainicio');
          $table->dateTime('datafim');
          $table->unsignedInteger('mediadias');
          $table->timestamps();

          $table->foreign('cliente_id')->references('id')->on('clientes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('clientepromocaos');
    }
}
