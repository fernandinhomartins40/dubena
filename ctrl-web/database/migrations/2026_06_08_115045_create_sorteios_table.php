<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSorteiosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sorteios', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger("grupo_id");
            $table->unsignedBigInteger("empresa_id");
            $table->unsignedBigInteger("pedido_id");
            $table->unsignedBigInteger("cliente_id");
            $table->date("datainicio");
            $table->date("datafim");
            $table->dateTime("datasorteio");
            $table->boolean("app")->default(false);
            $table->timestamps();

            $table->foreign("grupo_id")->references("id")->on("empresas_grupos");
            $table->foreign("empresa_id")->references("id")->on("empresas");
            $table->foreign("cliente_id")->references("id")->on("clientes");
            $table->foreign("pedido_id")->references("id")->on("pedidos");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sorteios');
    }
}
