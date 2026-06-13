<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterVuntribNfs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfemitidaitems', function (Blueprint $table) {
            $table->dropColumn('vuntrib');
        });
        Schema::table('nfrecebidaitems', function (Blueprint $table) {
            $table->dropColumn('vuntrib');
        });
        Schema::table('nfemitidaitems', function (Blueprint $table) {
            $table->decimal('vuntrib', 21, 10)->default(0);
        });
        Schema::table('nfrecebidaitems', function (Blueprint $table) {
            $table->decimal('vuntrib', 21, 10)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfemitidaitems', function (Blueprint $table) {
            //
        });
    }
}
