<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateContaextratoconfigsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contaextratoconfigs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('descricao');
            $table->unsignedInteger('conta_id');
            $table->unsignedInteger('condicaopagamento_id')->nullable()->default(null);
            $table->unsignedInteger('planoconta_id')->nullable()->default(null);
            $table->unsignedInteger('centrocusto_id')->nullable()->default(null);
            $table->unsignedInteger('cliente_id')->nullable()->default(null);
            $table->unsignedInteger('contamovimentotipo_id')->nullable()->default(null);
            $table->unsignedInteger('acao')->nullable()->default(null);
            $table->unsignedInteger('contaorigem_id')->nullable()->default(null);
            $table->foreign('conta_id')->references('id')->on('contas')->onDelete('cascade');
            $table->foreign('contaorigem_id')->references('id')->on('contas')->onDelete('cascade');
            $table->foreign('condicaopagamento_id')->references('id')->on('condicaopagamentos')->onDelete('cascade');
            $table->foreign('planoconta_id')->references('id')->on('planocontas')->onDelete('cascade');
            $table->foreign('centrocusto_id')->references('id')->on('centrocustos')->onDelete('cascade');
            $table->foreign('cliente_id')->references('id')->on('clientes')->onDelete('cascade');
            $table->foreign('contamovimentotipo_id')->references('id')->on('contamovimentotipos')->onDelete('cascade');
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
        Schema::dropIfExists('contaextratoconfigs');
    }
}
