<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateClientecontatosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('clientecontatos', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('cliente_id');
          $table->unsignedInteger('situacao_id');
          $table->unsignedInteger('tipo_id');
          $table->unsignedInteger('responsavel_id')->nullable()->default(null);
          $table->dateTime('datahora');
          $table->string('descricao');
          $table->string('acao');

          $table->timestamps();
          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id')->references('id')->on('empresas');
          $table->foreign('cliente_id')->references('id')->on('clientes')->onDelete('cascade');;
          $table->foreign('situacao_id')->references('id')->on('clientecontatosituacaos');
          $table->foreign('tipo_id')->references('id')->on('clientecontatotipos');
          $table->foreign('responsavel_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('clientecontatos');
    }
}
