<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterProdutoleiimpostoTable extends Migration
{
  /**
  * Run the migrations.
  *
  * @return void
  */
  public function up()
  {
    Schema::table('produtoleiimpostos', function (Blueprint $table) {
      $table->dropColumn('descricao');
    });
    Schema::table('produtoleiimpostos', function (Blueprint $table) {
      $table->string('descricao', 500)->nullable();
    });
  }

  /**
  * Reverse the migrations.
  *
  * @return void
  */
  public function down()
  {
    //
  }
}
