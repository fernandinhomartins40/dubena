<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateValegasvendasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('valegasvendas', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('cliente_id');
          $table->unsignedInteger('financeiro_id');
          $table->unsignedInteger('condicaopagamento_id');
          $table->unsignedInteger('produto_id');

          $table->decimal('quantidade', 15, 4);
          $table->decimal('valorunitario', 15, 4);
          $table->decimal('valortotal', 15, 4);
          $table->date('datavenda');
          $table->boolean('prevenda')->default(false);
          $table->decimal('prevendaquantidade', 15, 4);
          $table->boolean('cancelado')->default(false);

          $table->timestamps();

          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id')->references('id')->on('empresas');
          $table->foreign('cliente_id')->references('id')->on('clientes');
          $table->foreign('financeiro_id')->references('id')->on('financeiros');
          $table->foreign('condicaopagamento_id')->references('id')->on('condicaopagamentos');
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
        Schema::drop('valegasvendas');
    }
}
