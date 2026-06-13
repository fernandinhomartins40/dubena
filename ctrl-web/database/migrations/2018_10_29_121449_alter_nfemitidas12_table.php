<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfemitidas12Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfemitidas', function (Blueprint $table) {
            $table->tinyInteger("iddest")->default(1);
            $table->tinyInteger("indfinal")->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfemitidas', function (Blueprint $table) {
            $table->dropColumn(["iddest", "indfinal"]);
        });
    }
}
