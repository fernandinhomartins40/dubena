<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePosvendaperguntasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('posvendaperguntas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('posvenda_id');

            $table->string('descricao', 500);

            $table->timestamps();

            $table->foreign('posvenda_id', 'posvendaperg_posvendas_foreign')->references('id')->on('posvendas')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('posvendaperguntas');
    }
}
