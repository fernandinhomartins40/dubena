<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfimposto7Table extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfimpostos',
                function (Blueprint $table) {
            $table->decimal('taxafecop', 10, 2)->nullable();
            
            $table->dropColumn(['pfnficmsbase', 'nficmsbase']);
        });
        Schema::table('nfimpostos',
                function (Blueprint $table) {
            $table->decimal('nficmsbase', 10, 4)->nullable();
            $table->decimal('pfnficmsbase', 10, 4)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfimpostos',
                function (Blueprint $table) {
            $table->dropColumn('taxafecop');
        });
    }

}
