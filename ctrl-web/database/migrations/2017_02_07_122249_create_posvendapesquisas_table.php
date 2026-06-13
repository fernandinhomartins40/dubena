<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePosvendapesquisasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('posvendapesquisas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cliente_id')->nullable()->default(null);
            $table->unsignedInteger('setor_id')->nullable()->default(null);
            $table->unsignedInteger('pedido_id')->nullable()->default(null);
            $table->unsignedInteger('posvenda_id');

            $table->dateTime('datahora')->nullable()->default(null);
            $table->string('observacao', 500)->nullable()->default(null);

            $table->timestamps();

            $table->foreign('cliente_id')->references('id')->on('clientes');
            $table->foreign('setor_id')->references('id')->on('setors');
            $table->foreign('pedido_id')->references('id')->on('pedidos');
            $table->foreign('posvenda_id')->references('id')->on('posvendas')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('posvendapesquisas');
    }
}
