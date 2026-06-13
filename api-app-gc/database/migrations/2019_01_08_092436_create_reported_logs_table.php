<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateReportedLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection("sgcm_logs")->dropIfExists('reported_logs');
        Schema::connection("sgcm_logs")->create('reported_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->dateTime("datetime");
            $table->longText("content");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection("sgcm_logs")->dropIfExists('reported_logs');
    }
}
