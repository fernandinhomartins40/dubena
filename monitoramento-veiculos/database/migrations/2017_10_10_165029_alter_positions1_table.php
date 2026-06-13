<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterPositions1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->float('latitude', 23,15)->nullable()->default(null)->change();
            $table->float('longitude', 23,15)->nullable()->default(null)->change();
			$table->float('altitude', 23,15)->nullable()->default(null)->change();
			$table->float('course', 23,15)->nullable()->default(null)->change();
			$table->float('speed', 23,15)->nullable()->default(null)->change();	
			$table->float('power', 23,15)->nullable()->default(null)->change();
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
