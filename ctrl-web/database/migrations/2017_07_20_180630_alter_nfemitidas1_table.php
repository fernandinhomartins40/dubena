<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfemitidas1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('nfemitidas', function (Blueprint $table) {
          $table->dropColumn('numeroreciboenvio');
      });

      Schema::table('nfemitidas', function (Blueprint $table) {
          $table->string('numeroreciboenvio',20)->nullable()->default(null);
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
