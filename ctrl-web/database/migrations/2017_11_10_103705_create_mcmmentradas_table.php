<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMcmmentradasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mcmmhistoricoentradas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('nfemitida_id')->nullable()->default(null);
            $table->unsignedInteger('nfrecebida_id')->nullable()->default(null);
            $table->unsignedInteger('mcmm_id');
            $table->unsignedInteger('qdep02');
            $table->unsignedInteger('qdep08');
            $table->unsignedInteger('qdep13');
            $table->unsignedInteger('qdep20');
            $table->unsignedInteger('qdep45');
            $table->unsignedInteger('qdep90');
            $table->boolean('original')->default(false);
            $table->boolean('total')->default(false);
            $table->boolean('mesanterior')->default(false);
            $table->boolean('em_uso')->default(false);

            $table->timestamps();
            $table->foreign('nfemitida_id')->references('id')->on('nfemitidas');
            $table->foreign('nfrecebida_id')->references('id')->on('nfrecebidas');
            $table->foreign('mcmm_id')->references('id')->on('mcmms');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('mcmmentradas', function (Blueprint $table) {
            //
        });
    }
}
