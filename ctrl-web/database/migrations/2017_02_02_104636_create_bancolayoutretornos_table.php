<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBancolayoutretornosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bancolayoutretornos', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('banco_id')->nullable()->default(null);
          $table->string('descricao', 100);
          $table->string('separador', 50);
          $table->string('campos', 500);
          $table->string('colunas', 500);
          $table->string('tipoarquivo', 10);
          $table->unsignedInteger('linhainicio');
          $table->boolean('ativo')->default(true);

          $table->timestamps();

          $table->foreign('banco_id')->references('id')->on('bancos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('bancolayoutretornos');
    }
}
