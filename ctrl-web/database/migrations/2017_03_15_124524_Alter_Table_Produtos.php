<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableProdutos extends Migration
{
  /**
  * Run the migrations.
  *
  * @return void
  */
  public function up()
  {
    Schema::table('produtos', function (Blueprint $table) {

      // $table->unsignedInteger('nfcest_id')->nullable();
      // $table->unsignedInteger('nfipi_id')->nullable();
      // $table->decimal('nfealiqipi', 15, 4);
      // $table->decimal('nfebcipi', 15, 4);

      // $table->foreign('nfcest_id')->references('id')->on('nfcests');
      // $table->foreign('nfipi_id')->references('id')->on('nfipis');

    });
  }

  /**
  * Reverse the migrations.
  *
  * @return void
  */
  public function down()
  {
    Schema::table('produtos', function (Blueprint $table) {
      $table->dropColumn(['nfcest_id', 'nfipi_id', 'nfealiqipi', 'nfebcipi']);
    });
  }
}
