<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCondicaopagamentoparcelasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('condicaopagamentoparcelas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('condicaopagamento_id');
            $table->unsignedInteger('dias');
            $table->decimal('percentualvalor', 5, 2);
            $table->timestamps();

            $table->foreign('condicaopagamento_id')->references('id')->on('condicaopagamentos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('condicaopagamentoparcelas');
    }
}
