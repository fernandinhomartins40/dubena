<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterUltimaposicaos1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         Schema::table('ultimaposicaos', function (Blueprint $table) {
            $table->unsignedInteger('deviceid')->nullable()->default(null);
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
