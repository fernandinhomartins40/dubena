<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateComodatosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('comodatos', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('cliente_id');
          $table->date('datacontrato');
          $table->date('datavencimento');
          $table->string('numeronota', 20);
          $table->string('observacao', 500);
          $table->string('entradasaida', 1);//'S'-Saida 'E'-Entrada
          $table->boolean('ativo')->default(true);

          $table->timestamps();

          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id')->references('id')->on('empresas');
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
        Schema::drop('comodatos');
    }
}
