<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEstoquefechamentosetorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('estoquefechamentosetors', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('setor_id');
          $table->unsignedInteger('estoquefechamento_id');
          $table->unsignedInteger('produto_id');
          $table->decimal('quantidade', 15, 4);
          $table->decimal('customedio', 15, 4);
          $table->decimal('precovenda', 15, 4);

          $table->timestamps();

          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id')->references('id')->on('empresas');
          $table->foreign('setor_id')->references('id')->on('setors');
          $table->foreign('estoquefechamento_id')->references('id')->on('estoquefechamentos')->onDelete('cascade');
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
        Schema::drop('estoquefechamentosetors');
    }
}
