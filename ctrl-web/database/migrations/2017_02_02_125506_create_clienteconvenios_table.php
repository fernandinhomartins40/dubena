<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateClienteconveniosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('clienteconvenios', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('cliente_id');
          $table->date('datacontrato')->nullable()->default(null);
          $table->unsignedInteger('diafechamento');
          $table->unsignedInteger('diavencimento');
          $table->decimal('comissao', 5, 2);
          $table->unsignedInteger('comissaodestino')->default(0);//0-Funcionario 1-Empresa
          $table->unsignedInteger('limitecompra');
          $table->timestamps();

          $table->foreign('cliente_id')->references('id')->on('clientes');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('clienteconvenios');
    }
}
