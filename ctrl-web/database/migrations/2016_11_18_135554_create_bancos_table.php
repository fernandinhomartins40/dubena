<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBancosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bancos', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('codigo');
          $table->string('descricao', 100);
          $table->string('site', 100);
          $table->boolean('ativo')->default(true);

          $table->timestamps();
          $table->foreign('grupo_id', 'banco_grupo_foreign')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id', 'banco_empresa_foreign')->references('id')->on('empresas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('bancos');
    }
}
