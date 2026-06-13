<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePedidoSituacoesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sgcm_api')->create('pedidosituacoes', function (Blueprint $table) {
            $table->increments('id');
            $table->string("descricao");
            $table->boolean("pendente")->default(false);
            $table->boolean("ementrega")->default(false);
            $table->boolean("entregue")->default(false);
            $table->boolean("cancelado")->default(false);
            $table->boolean("ativo")->default(false);

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
        Schema::connection('sgcm_api')->dropIfExists('pedidosituacoes');
    }
}
