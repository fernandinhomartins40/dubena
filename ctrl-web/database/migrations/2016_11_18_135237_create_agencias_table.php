<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAgenciasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('agencias', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('banco_id');
          $table->unsignedInteger('cidade_id');
          $table->unsignedInteger('bairro_id');
          $table->unsignedInteger('agencia');
          $table->unsignedInteger('agenciadigito');
          $table->unsignedInteger('postobeneficiario');
          $table->string('descricao', 100);
          $table->boolean('ativo')->default(true);

          $table->timestamps();
          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id', 'agencia_empresa_foreign')->references('id')->on('empresas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('agencias');
    }
}
