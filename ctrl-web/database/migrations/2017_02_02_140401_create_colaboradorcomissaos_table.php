<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateColaboradorcomissaosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('colaboradorcomissaos', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('colaborador_id');
          $table->unsignedInteger('condicaopagamento_id');
          $table->unsignedInteger('produto_id');
          $table->unsignedInteger('setor_id');
          $table->decimal('percentual', 5, 2);
          $table->decimal('empresavalor', 15, 4);
          $table->date('datainicio');
          $table->date('datafim');
          $table->boolean('ativo')->default(true);

          $table->timestamps();

          $table->foreign('colaborador_id')->references('id')->on('colaboradors')->onDelete('cascade');
          $table->foreign('condicaopagamento_id')->references('id')->on('condicaopagamentos')->onDelete('cascade');
          $table->foreign('produto_id')->references('id')->on('produtos')->onDelete('cascade');
          $table->foreign('setor_id')->references('id')->on('setors')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('colaboradorcomissaos');
    }
}
