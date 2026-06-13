<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVendaativaclientesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vendaativaclientes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('vendaativa_id');
            $table->unsignedInteger('cliente_id');
            $table->unsignedInteger('pedido_id')->nullable()->default(null);
            $table->unsignedInteger('vendaativaocorrencia_id')->nullable()->default(null);

            $table->dateTime('datahora');
            $table->unsignedInteger('gerou')->default(0);//0=Nada, 1=Pedido, 2=Ocorrencia
            $table->boolean('ligarnovamente')->default(false);

            $table->timestamps();

            $table->foreign('vendaativa_id')->references('id')->on('vendaativas')->onDelete('cascade');
            $table->foreign('cliente_id')->references('id')->on('clientes');
            $table->foreign('pedido_id')->references('id')->on('pedidos');
            $table->foreign('vendaativaocorrencia_id', 'venatvcli_venatvoco_foreign')->references('id')->on('vendaativaocorrencias');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('vendaativaclientes');
    }
}
