<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterRuasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ruas', function (Blueprint $table) {
            $table->dropColumn('bairro_id');
        });
        Schema::table('ruas', function (Blueprint $table) {
            $table->integer('bairro_id')->nullable()->default(null);
            $table->foreign('bairro_id')->references('id')->on('bairros')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ruas', function (Blueprint $table) {
            //
        });
    }
}
