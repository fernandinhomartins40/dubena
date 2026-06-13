<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableEntradasaidapedidos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('veiculoentradasaidapedidos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('entradasaida_id');
            $table->unsignedInteger('pedido_id');
            $table->timestamps();

            $table->foreign('entradasaida_id')->references('id')
                ->on('veiculoentradasaidas')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('pedido_id')->references('id')->on('pedidos')->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('veiculoentradasaidapedidos');
        $this->up();
    }
}
