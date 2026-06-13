<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCondicaoPagamentoImportacoesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sgcm_api')->create('condicaopagamentoimportacoes', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            
            $table->boolean("ativo")->default(false);

            $table->unsignedInteger("erp_id");
            $table->unsignedInteger("user_id")->index();
            $table->foreign("user_id")->references("id")->on("users");

            $table->unsignedInteger("condicaopagamento_id")->index();
            $table->foreign("condicaopagamento_id")->references("id")->on("condicaopagamentos");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sgcm_api')->dropIfExists('condicaopagamentoimportacoes');
    }
}
