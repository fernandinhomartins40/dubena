<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateClienteprodutosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('clienteprodutos', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('produto_id');
          $table->unsignedInteger('cliente_id');
          $table->decimal('preco', 15, 4);
          $table->timestamps();

          $table->foreign('produto_id')->references('id')->on('produtos')->onDelete('cascade');
          $table->foreign('cliente_id')->references('id')->on('clientes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('clienteprodutos');
    }
}
