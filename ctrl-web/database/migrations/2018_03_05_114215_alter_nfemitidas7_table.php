<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfemitidas7Table extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfemitidas', function (Blueprint $table) {
            $table->dropColumn('destie');
        });
        Schema::table('nfemitidas',
                function (Blueprint $table) {
            $table->string('destie', 14)->nullable()->default(null);
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
            $table->dropColumn('destie');
        });
        Schema::table('nfemitidas',
                function (Blueprint $table) {
            $table->string('destie', 14)->nullable()->default(null);
        });
    }

}
