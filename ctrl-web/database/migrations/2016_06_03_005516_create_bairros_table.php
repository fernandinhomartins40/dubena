<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBairrosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bairros', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('cidade_id');
            $table->string('descricao');
            $table->timestamps();
            $table->foreign('cidade_id')->references('id')->on('cidades');
            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
            $table->unique(array('grupo_id', 'cidade_id', 'descricao'));
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('bairros');
    }
}
