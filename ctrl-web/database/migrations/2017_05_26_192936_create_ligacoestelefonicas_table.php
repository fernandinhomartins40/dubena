<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLigacoestelefonicasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ligacoestelefonicas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pedido_id')->nullable();
            $table->unsignedInteger('motivonaovenda_id')->nullable();
            $table->unsignedInteger('cliente_id')->nullable();
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('grupo_id');
            $table->boolean('rejeitada')->default(false);
            $table->string('telefone', 20);
            $table->string('linha', 45)->nullable();
            $table->dateTime('datahorainicio')->nullable();
            $table->dateTime('datahorafim')->nullable();

            $table->timestamps();
            $table->foreign('pedido_id')->references('id')->on('pedidos')->onUpdate('cascade');
            $table->foreign('motivonaovenda_id')->references('id')->on('motivonaovendas')->onUpdate('cascade');
            $table->foreign('cliente_id')->references('id')->on('clientes')->onUpdate('cascade');
            $table->foreign('empresa_id')->references('id')->on('empresas')->onUpdate('cascade');
            $table->foreign('grupo_id')->references('id')->on('empresas_grupos')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
