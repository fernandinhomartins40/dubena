<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableValegas2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('valegas', function (Blueprint $table) {
            $table->dropColumn('prevendasequencia');
        });
        Schema::table('valegas', function (Blueprint $table) {
            $table->integer('prevendasequencia')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('valegas', function (Blueprint $table) {
            //
        });
    }
}
