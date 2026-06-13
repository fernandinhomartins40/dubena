<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterDeviceidTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->index(['deviceid', 'dhposition']);
        });
        Schema::table('veiculos', function (Blueprint $table) {
            $table->string('deviceid')->nullable()->default(null);
        });
        Schema::table('veiculos', function (Blueprint $table) {
            $table->index(['deviceid']);
        });
        Schema::table('ultimaposicaos', function (Blueprint $table) {
            $table->index(['deviceid']);
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
