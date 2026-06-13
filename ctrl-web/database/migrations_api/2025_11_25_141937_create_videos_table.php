<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVideosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sgcm_api')->create('videos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger("user_id")->index();
            $table->string('titulo')->nullable();
            $table->string('url')->nullable();
            $table->boolean("ativo")->default(false)->nullable();
            $table->timestamps();

            $table->foreign("user_id")->references("id")->on("users");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sgcm_api')->dropIfExists('videos');
    }
}
