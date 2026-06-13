<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProdutosconvenioTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('clienteprodutosconvenios', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cliente_id');
            $table->float('preco', 8,2);
            $table->unsignedInteger('produto_id');

            $table->timestamps();

            $table->foreign('cliente_id')->on('clientes')->references('id');
            $table->foreign('produto_id')->on('produtos')->references('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('clienteprodutosconvenios');
    }
}
