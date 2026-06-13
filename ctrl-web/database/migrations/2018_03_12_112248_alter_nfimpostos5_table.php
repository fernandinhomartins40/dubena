<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfimpostos5Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfimpostos', function (Blueprint $table) {
            $table->dropColumn('pfaliqicmsst');
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
            $table->decimal('pfaliqicmsst', 10, 2)->nullable();
        });
    }
}
