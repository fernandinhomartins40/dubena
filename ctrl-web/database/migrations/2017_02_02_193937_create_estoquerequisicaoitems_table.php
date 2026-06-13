<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEstoquerequisicaoitemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('estoquerequisicaoitems', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('estoquerequisicao_id');
          $table->unsignedInteger('produto_id');
          $table->unsignedInteger('setor_id');
          $table->decimal('quantidade', 15, 4);
          $table->decimal('customedio', 15, 4);
          $table->string('entradasaida', 1);//'E'-Entrada 'S'-Saida

          $table->timestamps();

          $table->foreign('estoquerequisicao_id')->references('id')->on('estoquerequisicaos');
          $table->foreign('produto_id')->references('id')->on('produtos');
          $table->foreign('setor_id')->references('id')->on('setors');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('estoquerequisicaoitems');
    }
}
