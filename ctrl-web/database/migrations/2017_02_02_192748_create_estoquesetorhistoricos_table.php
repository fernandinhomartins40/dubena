<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEstoquesetorhistoricosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('estoquesetorhistoricos', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('user_id');
          $table->unsignedInteger('setor_id');
          $table->unsignedInteger('produto_id');
          $table->string('movimentacao', 50);
          $table->decimal('quantidade', 15, 4);
          $table->string('motivo', 500);
          $table->dateTime('datahora');
          $table->dateTime('datahoracompetencia');
          $table->string('entidade', 100);
          $table->unsignedInteger('entidade_id');

          $table->timestamps();

          $table->foreign('user_id')->references('id')->on('users');
          $table->foreign('setor_id')->references('id')->on('setors');
          $table->foreign('produto_id')->references('id')->on('produtos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('estoquesetorhistoricos');
    }
}
