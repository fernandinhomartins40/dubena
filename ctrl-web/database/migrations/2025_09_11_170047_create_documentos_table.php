<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDocumentosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('documentos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('documentotipo_id');
            $table->unsignedInteger('colaborador_id');
            $table->string('descricao', 500);
            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('documentotipo_id')->references('id')->on('documentotipos');
            $table->foreign('colaborador_id')->references('id')->on('colaboradors');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('documentos');
    }
}
