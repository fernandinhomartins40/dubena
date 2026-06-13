z<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableNfimpostos2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfimpostos', function (Blueprint $table) {
            $table->dropColumn('mva');
            $table->dropColumn('mvareduzido');
            $table->dropColumn('pfmva');
        });
        Schema::table('nfimpostos', function (Blueprint $table) {
            $table->decimal('mva', 10, 2)->nullable();
            $table->decimal('pfmva', 10, 2)->nullable();
            $table->decimal('mvareduzido', 10, 2)->nullable();
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
            //
        });
    }
}
