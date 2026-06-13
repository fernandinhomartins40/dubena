<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAppnotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('appnotifications', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');
            $table->string("fcmtitle", 150);
            $table->string("fcmbody", 500);
            $table->string("status", 50);
            $table->boolean("instant")->default(false);
            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
            $table->foreign('empresa_id')->references('id')->on('empresas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('appnotifications');
    }
}
