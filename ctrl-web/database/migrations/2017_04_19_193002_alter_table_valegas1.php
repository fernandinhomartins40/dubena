<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableValegas1 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('valegas', function (Blueprint $table) {
            $table->dropColumn('databaixa');
            $table->dropColumn('datagerecao');
        });
        Schema::table('valegas', function (Blueprint $table) {
            $table->date('databaixa')->nullable();
            $table->date('datageracao')->nullable();
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
