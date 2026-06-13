<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMcmmsaidasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mcmmhistoricosaidas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('mcmm_id');
            $table->unsignedInteger('qdep02');
            $table->unsignedInteger('qdep08');
            $table->unsignedInteger('qdep13');
            $table->unsignedInteger('qdep20');
            $table->unsignedInteger('qdep45');
            $table->unsignedInteger('qdep90');
            $table->unsignedInteger('operacao')->default(0); // 0 => sem operações 1 => normal, 2=> Repres..etc.., 3=> outras
            $table->boolean('somames')->default(false);
            $table->boolean('somaseguinte')->default(false);
            $table->boolean('original')->default(false);
            $table->boolean('em_uso')->default(false);
            
            $table->timestamps();
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
        Schema::table('mcmmsaidas', function (Blueprint $table) {
            //
        });
    }
}
