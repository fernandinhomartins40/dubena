<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePedidoSituacaoImportacoesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sgcm_api')->create('pedidosituacaoimportacoes', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();

            $table->boolean("ativo")->default(false);

            $table->unsignedInteger("erp_id");

            $table->unsignedInteger("pedidosituacao_id");
            $table->foreign("pedidosituacao_id")->references("id")->on("pedidosituacoes");

            $table->unsignedInteger("user_id")->index();
            $table->foreign("user_id")->references("id")->on("users");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sgcm_api')->dropIfExists('pedidosituacaoimportacoes');
    }
}
