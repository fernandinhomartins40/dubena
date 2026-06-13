<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEmpresabemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('empresabems', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->string('descricao', 100);
          $table->decimal('valor', 15, 4);
          $table->decimal('valororiginal', 20, 9);
          $table->string('numeroserie', 30);
          $table->decimal('valoratual', 20, 9);
          $table->decimal('depreciacaovalor', 20, 9);
          $table->decimal('depreciacaoporcentagem', 20, 9);
          $table->unsignedInteger('depreciacaodias');
          $table->boolean('ativo')->default(true);

          $table->timestamps();

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
        Schema::drop('empresabems');
    }
}
