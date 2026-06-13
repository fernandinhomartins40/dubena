<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePedidosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();

            $table->string('observacoes', 200)->nullable()->default(null);

            $table->dateTime("datahoraenvioentregador")->nullable()->default(null);
            $table->dateTime("datahoraentrega")->nullable()->default(null);
            $table->dateTime("datahoracancelamento")->nullable()->default(null);

            $table->unsignedInteger("condicaopagamento_id")->index();
            $table->foreign("condicaopagamento_id")->references("id")->on("condicaopagamentos");

            $table->unsignedInteger("pedidosituacao_id")->index();
            $table->foreign("pedidosituacao_id")->references("id")->on("pedidosituacoes");

            $table->unsignedInteger("cliente_id")->index();
            $table->foreign("cliente_id")->references("id")->on("clienteimportacoes");

            $table->unsignedInteger("endereco_id")->index();
            $table->foreign("endereco_id")->references("id")->on("clienteenderecos");

            $table->unsignedInteger("erp_id")->nullable()->default(null);

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
        Schema::dropIfExists('pedidos');
    }
}
