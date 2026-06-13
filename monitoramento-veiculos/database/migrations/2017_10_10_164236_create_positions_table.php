<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePositionsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->float('latitude', 23, 15)->nullable()->default(null);
            $table->float('longitude', 23, 15)->nullable()->default(null);
            $table->float('altitude', 23, 15)->nullable()->default(null);
            $table->float('course', 23, 15)->nullable()->default(null);
            $table->float('speed', 23, 15)->nullable()->default(null);
            $table->string('deviceid')->nullable()->default(null);
            $table->dateTime('dhposition')->nullable()->default(null);
            $table->string('address')->nullable()->default(null);
            $table->float('power', 23, 15)->nullable()->default(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('positions');
    }

}
