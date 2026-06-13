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
          $table->string('endereco')->nullable()->default(null);
          $table->string('numero')->nullable()->default(null);
          $table->string('complemento')->nullable()->default(null);
          $table->string('telefone1')->nullable()->default(null);
          $table->string('telefone2')->nullable()->default(null);
          $table->string('email')->nullable()->default(null);
          $table->unsignedInteger('cidade_id');
          $table->string('cep');
          $table->unsignedInteger('bairro_id');
          $table->boolean('ativo');
          $table->timestamps();
          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('bairro_id')->references('id')->on('bairros');
          $table->foreign('cidade_id')->references('id')->on('cidades');
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
