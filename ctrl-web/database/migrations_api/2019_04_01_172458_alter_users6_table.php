<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterUsers6Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sgcm_api')->table('users', function (Blueprint $table) {
            $table->integer( "delivery_time_start")->default(5);
            $table->integer( "delivery_time_end")->default(15);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sgcm_api')->table('users', function (Blueprint $table) {
            $table->dropColumn(["delivery_time_start", "delivery_time_end"]);
        });
    }
}
