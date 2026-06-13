<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableClientes extends Migration
{
  /**
  * Run the migrations.
  *
  * @return void
  */
  public function up()
  {
    Schema::table('clientes', function (Blueprint $table) {
      $table->unsignedInteger('condicaopagamento_id')->nullable();
      $table->foreign('condicaopagamento_id')->references('id')->on('condicaopagamentos');
    });
  }

  /**
  * Reverse the migrations.
  *
  * @return void
  */
  public function down()
  {
    Schema::table('clientes', function (Blueprint $table) {
      $table->dropColumn(['condicaopagamento_id']);
    });
  }
}
