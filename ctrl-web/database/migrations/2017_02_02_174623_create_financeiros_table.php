<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFinanceirosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('financeiros', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('cliente_id');
          $table->unsignedInteger('condicaopagamento_id');
          $table->string('descricao', 500);
          $table->string('documento', 50);
          $table->decimal('valor', 15, 4);
          $table->decimal('dataemissao', 15, 4);
          $table->decimal('datacompetencia', 15, 4);
          $table->string('pagarreceber', 1);//'P'-Pagar, 'R'-Receber
          $table->string('cartaoautorizacao', 20);
          $table->string('cartaonsu', 20);
          $table->boolean('boletogerado')->default(false);
          $table->unsignedInteger('agrupamentostatus');//0-Normal 1-Agrupador 2-Agrupado
          $table->unsignedInteger('agrupadorfinanceiro_id')->nullable()->default(null);

          $table->timestamps();

          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id')->references('id')->on('empresas');
          $table->foreign('cliente_id')->references('id')->on('clientes');
          $table->foreign('condicaopagamento_id')->references('id')->on('condicaopagamentos');
          $table->foreign('agrupadorfinanceiro_id')->references('id')->on('financeiros');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('financeiros');
    }
}
