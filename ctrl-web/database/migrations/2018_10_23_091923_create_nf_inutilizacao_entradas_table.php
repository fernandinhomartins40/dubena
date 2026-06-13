<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNfInutilizacaoEntradasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nfinutilizacaoentradas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('user_id');
            $table->dateTime('datahora');
            $table->string('xjust', 255);
            $table->unsignedInteger('nfnumero');
            $table->char('nfmodelo', 2);
            $table->unsignedInteger('nfserie');
            $table->unsignedInteger('nfsituacao_id')->nullable()->default(null);

            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('nfsituacao_id')->references('id')->on('nfsituacaos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('nfinutilizacaoentradas');
    }
}
