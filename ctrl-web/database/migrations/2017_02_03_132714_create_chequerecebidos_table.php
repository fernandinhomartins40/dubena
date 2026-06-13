<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateChequerecebidosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('chequerecebidos', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('banco_id');
          $table->unsignedInteger('chequesituacao_id');
          $table->unsignedInteger('chequesituacaoanterior_id')->nullable()->dafault(null);
          $table->unsignedInteger('depositoconta_id')->nullable()->dafault(null);
          $table->unsignedInteger('baixaconta_id')->nullable()->dafault(null);

          $table->string('numeroconta');
          $table->unsignedInteger('numerocheque');
          $table->date('dataemissao');
          $table->date('datavencimento');
          $table->date('datacompetencia');
          $table->date('datapagamento');
          $table->date('datadeposito')->nullable()->dafault(null);
          $table->date('datadevolucao')->nullable()->dafault(null);
          $table->decimal('valor', 15, 4);
          $table->string('observacao', 500);

          $table->timestamps();

          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id')->references('id')->on('empresas');
          $table->foreign('banco_id')->references('id')->on('bancos');
          $table->foreign('chequesituacao_id', 'chequerec_situacao_foreign')->references('id')->on('chequesituacaos');
          $table->foreign('chequesituacaoanterior_id', 'chequerec_situacaoant_foreign')->references('id')->on('chequesituacaos');
          $table->foreign('depositoconta_id')->references('id')->on('contas');
          $table->foreign('baixaconta_id')->references('id')->on('contas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('chequerecebidos');
    }
}
