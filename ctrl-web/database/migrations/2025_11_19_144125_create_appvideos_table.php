<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAppvideosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('appvideos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');
            $table->string('titulo')->nullable();
            $table->string('caminho')->nullable();
            $table->string('mensagem', 400)->nullable();
            $table->unsignedTinyInteger('status')->nullable();
            $table->boolean('ativo');
            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('empresas_grupos')->onUpdate('cascade');
            $table->foreign('empresa_id')->references('id')->on('empresas')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('appvideos');
    }
}
