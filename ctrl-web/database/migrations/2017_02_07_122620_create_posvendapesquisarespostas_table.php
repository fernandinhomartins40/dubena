<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePosvendapesquisarespostasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('posvendapesquisarespostas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('posvendapesquisa_id');
            $table->unsignedInteger('posvendaresposta_id');

            $table->timestamps();

            $table->foreign('posvendapesquisa_id', 'posvenpesres_posvenpes_foreign')->references('id')->on('posvendapesquisas')->onDelete('cascade');
            $table->foreign('posvendaresposta_id', 'posvenpesres_posvenres_foreign')->references('id')->on('posvendarespostas')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('posvendapesquisarespostas');
    }
}
