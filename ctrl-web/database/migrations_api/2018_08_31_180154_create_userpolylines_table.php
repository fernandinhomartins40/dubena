<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserpolylinesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sgcm_api')->create('userpolylines', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger("user_id");
            $table->foreign("user_id")->references("id")->on("users")->index();

            $table->float('latitude', 19,15)->nullable()->default(null);
            $table->float('longitude', 19,15)->nullable()->default(null);

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
        Schema::connection('sgcm_api')->dropIfExists('userpolylines');
    }
}
