<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSetorcolaboradoresTable3 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('setorcolaboradores', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('setor_id');
            $table->unsignedInteger('colaborador_id');
            $table->timestamps();
            $table->unique(['colaborador_id', 'setor_id']);
            $table->foreign('setor_id')->references('id')->on('setors');
            $table->foreign('colaborador_id', 'setorcolaborador_colaboradorfk')->references('id')->on('colaboradors');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('setorcolaboradores', function (Blueprint $table) {
            //
        });
    }
}
