<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateConveniofechamentosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('conveniofechamentos', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('cliente_id');
          $table->unsignedInteger('financeiro_id');
          $table->date('dataemissao');
          $table->date('datavencimento');
          $table->date('valor', 15, 4);

          $table->timestamps();

          $table->foreign('cliente_id')->references('id')->on('clientes');
          $table->foreign('financeiro_id')->references('id')->on('financeiros');
          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id')->references('id')->on('empresas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('conveniofechamentos');
    }
}
