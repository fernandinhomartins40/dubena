<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateChequeemitidosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('chequeemitidos', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('conta_id');
          $table->unsignedInteger('chequesituacao_id');
          $table->unsignedInteger('contatalao_id');

          $table->unsignedInteger('numerocheque');
          $table->date('dataemissao');
          $table->date('datavencimento');
          $table->date('datacompetencia');
          $table->date('datapagamento');
          $table->decimal('valor', 15, 4);
          $table->string('observacao', 500);

          $table->timestamps();

          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id')->references('id')->on('empresas');
          $table->foreign('conta_id')->references('id')->on('contas');
          $table->foreign('chequesituacao_id')->references('id')->on('chequesituacaos');
          $table->foreign('contatalao_id')->references('id')->on('contatalaos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('chequeemitidos');
    }
}
