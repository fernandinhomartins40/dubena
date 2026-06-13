<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLogcercasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('logcercas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger("empresa_id");
            $table->unsignedInteger("grupo_id");
            $table->unsignedInteger("setor_id");
            $table->unsignedInteger("colaborador_id")->nullable();
            $table->unsignedInteger("veiculo_id")->nullable();
            $table->dateTime("datahora");
            $table->string("cerca")->nullable();
            $table->unsignedInteger("cerca_id")->nullable();
            $table->string("placa")->nullable();
            $table->string("veiculo")->nullable();
            $table->string("motorista")->nullable();
            $table->float('latitude', 23, 15)->nullable()->default(null);
            $table->float('longitude', 23, 15)->nullable()->default(null);
            $table->unsignedTinyInteger("tipo");
            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('setor_id')->references('id')->on('setors');
            $table->foreign('colaborador_id')->references('id')->on('colaboradors');
            $table->foreign('veiculo_id')->references('id')->on('veiculos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('logcercas');
    }
}
