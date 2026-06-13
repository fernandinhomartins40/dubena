<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVendaativaocorrenciasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vendaativaocorrencias', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('cliente_id');
            $table->unsignedInteger('vendaativaocorrenciatipo_id');

            $table->dateTime('datahora');
            $table->string('observacao', 500);

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('cliente_id')->references('id')->on('clientes');
            $table->foreign('vendaativaocorrenciatipo_id', 'venativa_venativatip_foreign')->references('id')->on('vendaativaocorrenciatipos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('vendaativaocorrencias');
    }
}
