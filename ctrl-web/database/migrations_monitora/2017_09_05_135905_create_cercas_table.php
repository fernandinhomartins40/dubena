<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCercasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cercas', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('setor_id')->nullable()->default(null);
          $table->string('cor')->nullable()->default(null);
          $table->string('descricao')->nullable()->default(null);
          $table->boolean('ativo')->default(true);
          $table->timestamps();
          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id')->references('id')->on('empresas');
          $table->foreign('setor_id')->references('id')->on('setors');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cercas');
    }
}
