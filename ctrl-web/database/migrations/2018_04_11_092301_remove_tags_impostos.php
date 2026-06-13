<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveTagsImpostos extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nficms',
                function (Blueprint $table) {
            $table->dropColumn('tag');
        });
        Schema::table('nfpis',
                function (Blueprint $table) {
            $table->dropColumn('tag');
        });
        Schema::table('nfcofins',
                function (Blueprint $table) {
            $table->dropColumn('tag');
        });
        Schema::table('nfipis',
                function (Blueprint $table) {
            $table->dropColumn('tag');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nficms',
                function (Blueprint $table) {
            $table->string('tag', 10)->nullable()->default(null);
        });
        Schema::table('nfpis',
                function (Blueprint $table) {
            $table->string('tag', 10)->nullable()->default(null);
        });
        Schema::table('nfcofins',
                function (Blueprint $table) {
            $table->string('tag', 10)->nullable()->default(null);
        });
        Schema::table('nfipis',
                function (Blueprint $table) {
            $table->string('tag', 10)->nullable()->default(null);
        });
    }

}
