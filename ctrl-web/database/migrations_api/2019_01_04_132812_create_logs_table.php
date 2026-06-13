<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection("sgcm_logs")->create('logs', function (Blueprint $table) {
            $table->increments('id');
            $table->dateTime("datetime");
            $table->string("method")->nullable()->default(null);
            $table->string("uri")->nullable()->default(null);
            $table->string("message")->nullable()->default(null);
            $table->string("type");
            $table->string("parameters")->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection("sgcm_logs")->dropIfExists('logs');
    }
}
