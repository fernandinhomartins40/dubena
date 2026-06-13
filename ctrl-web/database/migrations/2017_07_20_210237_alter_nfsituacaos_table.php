<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfsituacaosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('nfsituacaos', function (Blueprint $table) {
          $table->dropColumn('msgerroreceita');
          $table->dropColumn('msgerrosistema');
      });

      Schema::table('nfsituacaos', function (Blueprint $table) {
          $table->string('msgerroreceita', 500);
          $table->string('msgerrosistema', 500);
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
