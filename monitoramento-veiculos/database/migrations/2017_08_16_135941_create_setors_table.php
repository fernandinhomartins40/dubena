<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSetorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('setors', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->string('descricao')->nullable()->default(null);
          $table->float('latitude', 23,15)->nullable()->default(null);
          $table->float('longitude', 23,15)->nullable()->default(null);
          $table->string('rua')->nullable()->default(null);
          $table->string('numero')->nullable()->default(null);
          $table->string('cep')->nullable()->default(null);
          $table->string('bairro')->nullable()->default(null);
          $table->string('cidade')->nullable()->default(null);
          $table->string('uf')->nullable()->default(null);
          
          $table->boolean('ativo')->default(true);
          $table->timestamps();
          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id')->references('id')->on('empresas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('setors');
    }
}
