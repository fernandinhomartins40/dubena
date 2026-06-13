<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProdutoCondicaoPagamentosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('produtocondicaopagamentos', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();

            $table->decimal('valor', 15, 4);

            $table->unsignedInteger("condicaopagamentoimportacao_id")->index();
            $table->foreign("condicaopagamentoimportacao_id")->references("id")->on("condicaopagamentoimportacoes");

            $table->unsignedInteger("produtoimportacao_id")->index();
            $table->foreign("produtoimportacao_id")->references("id")->on("produtoimportacoes");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('produtocondicaopagamentos');
    }
}
