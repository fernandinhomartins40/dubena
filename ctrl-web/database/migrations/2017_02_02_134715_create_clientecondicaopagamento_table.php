<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateClientecondicaopagamentoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::create('cliente_condicaopagamento', function (Blueprint $table)
      {
          $table->unsignedInteger('cliente_id')->index();
          $table->foreign('cliente_id')->references('id')->on('clientes')->onDelete('cascade');
          $table->unsignedInteger('condicaopagamento_id')->index();
          $table->foreign('condicaopagamento_id')->references('id')->on('condicaopagamentos')->onDelete('cascade');
          $table->timestamps();
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
