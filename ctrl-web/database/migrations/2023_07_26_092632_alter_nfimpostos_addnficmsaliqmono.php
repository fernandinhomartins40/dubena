<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfimpostosAddnficmsaliqmono extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfimpostos', function (Blueprint $table) {
            $table->decimal("nficmsalimono", 6, 4)->nullable();
            $table->decimal("pfnficmsalimono", 6, 4)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfimpostos', function (Blueprint $table) {
            $table->dropColumn("nficmsalimono");
            $table->dropColumn("pfnficmsalimono");
        });
    }
}
