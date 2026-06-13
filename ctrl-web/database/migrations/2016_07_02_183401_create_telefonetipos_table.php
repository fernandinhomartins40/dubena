<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTelefonetiposTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('telefonetipos', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');
            $table->string('descricao', 200);
            $table->boolean('ativo');

            $table->timestamps();
            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
            $table->foreign('empresa_id', 'telefonetipo_empresa_foreign')->references('id')->on('empresas');


        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('telefonetipos');
    }
}
