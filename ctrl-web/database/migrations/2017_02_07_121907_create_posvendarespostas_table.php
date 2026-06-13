<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePosvendarespostasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('posvendarespostas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('posvendapergunta_id');

            $table->string('descricao', 500);

            $table->timestamps();

            $table->foreign('posvendapergunta_id', 'posvenresp_posvenperg_foreign')->references('id')->on('posvendaperguntas')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('posvendarespostas');
    }
}
