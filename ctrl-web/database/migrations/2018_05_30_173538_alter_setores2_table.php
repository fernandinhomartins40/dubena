<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterSetores2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('setors', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
		
        Schema::table('setors', function (Blueprint $table) {
          $table->float('latitude', 17, 15)->nullable();
          $table->float('longitude', 17, 15)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('setors', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
        Schema::table('setors', function (Blueprint $table) {
          $table->float('latitude')->nullable();
          $table->float('longitude')->nullable();
        });
    }
}
