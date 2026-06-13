<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRuaincoignores extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inconsistencia_ignorada', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger("model_id");
            $table->string("model_type");

            $table->unsignedBigInteger("ignored_id");
            $table->string("ignored_type");

            $table->timestamps();

            $table->unique(["model_id", "model_type", "ignored_id", "ignored_type"], "ignored_pairs_unq");

            $table->index(['model_type', 'model_id']);
            $table->index(['ignored_type', 'ignored_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ruaincoignores');
    }
}
