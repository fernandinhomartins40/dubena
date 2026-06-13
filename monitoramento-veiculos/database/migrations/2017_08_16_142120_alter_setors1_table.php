<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterSetors1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Postgres: cast para double precision exige USING (helper trata por driver).
        \App\Helpers\MigrationHelper::toDouble('setors', 'latitude');
        \App\Helpers\MigrationHelper::toDouble('setors', 'longitude');
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
