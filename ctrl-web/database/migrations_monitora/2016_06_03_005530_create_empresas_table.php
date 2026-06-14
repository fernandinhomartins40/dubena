<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEmpresasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::create('empresas', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->string('razao_social');
          $table->string('nome_fantasia')->nullable()->default(null);
          $table->string('cnpj')->nullable()->default(null);
          $table->string('inscricao_estadual')->nullable()->default(null);
          $table->boolean('ativo');
          $table->timestamps();
          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
      });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
