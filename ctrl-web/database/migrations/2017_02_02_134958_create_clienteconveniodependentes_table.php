<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateClienteconveniodependentesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('clienteconveniodependentes', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('cliente_id');
          $table->unsignedInteger('parentesco_id');
          $table->string('nome', 100);
          $table->boolean('ativo')->default(true);
          $table->timestamps();

          $table->foreign('cliente_id')->references('id')->on('clientes');
          $table->foreign('parentesco_id')->references('id')->on('parentescos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('clienteconveniodependentes');
    }
}
