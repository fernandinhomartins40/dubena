<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfemitidasTable extends Migration
{
  /**
  * Run the migrations.
  *
  * @return void
  */
  public function up()
  {
    Schema::table('nfemitidas', function (Blueprint $table) {
      $table->dropColumn('emittelefone');
      $table->dropColumn('desttelefone');
    });
    Schema::table('nfemitidas', function (Blueprint $table) {
      $table->unsignedInteger('fretecondicaopagamento_id')->nullable()->default(null);
      $table->string('emittelefone', 20)->nullable()->default(null);
      $table->string('desttelefone', 20)->nullable()->default(null);
    });
  }

  /**
  * Reverse the migrations.
  *
  * @return void
  */
  public function down()
  {
    Schema::table('nfemitidas', function (Blueprint $table) {
      //
    });
  }
}
