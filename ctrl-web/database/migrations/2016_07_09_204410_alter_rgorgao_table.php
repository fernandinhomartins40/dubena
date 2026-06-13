<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterRgorgaoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('clientes', function (Blueprint $table) {
          $table->string('rgorgao', 15)->nullable()->default(null)->change();
      });
        // DB::statement("ALTER TABLE `clientes`
      	// CHANGE COLUMN `rgorgao` `rgorgao` VARCHAR(15) NOT NULL COLLATE 'utf8_unicode_ci'");
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
