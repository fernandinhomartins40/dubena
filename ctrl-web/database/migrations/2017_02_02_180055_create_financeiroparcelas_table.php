<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFinanceiroparcelasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('financeiroparcelas', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('financeiro_id');
          $table->unsignedInteger('numero');
          $table->date('datavencimento');
          $table->dateTime('datahorapagamento');
          $table->date('datacompetencia');
          $table->decimal('valor', 15, 4);
          $table->decimal('multa', 15, 4);
          $table->decimal('juros', 15, 4);
          $table->decimal('desconto', 15, 4);
          $table->decimal('valorefetivado', 15, 4);
          $table->string('pagarreceber', 1);//'P'-Pagar, 'R'-Receber
          $table->boolean('baixado')->default(false);

          $table->timestamps();

          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id')->references('id')->on('empresas');
          $table->foreign('financeiro_id')->references('id')->on('financeiros');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('financeiroparcelas');
    }
}
