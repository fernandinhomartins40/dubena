<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableNfimpostos4 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfimpostos', function (Blueprint $table) {
            $table->dropColumn('reducaoicms');
            $table->dropColumn('pfreducaoicms');
            $table->dropColumn('pftaxafecop');
            $table->dropColumn('nfpisaliqcred');
            $table->dropColumn('nfcofinsaliqcred');
        });
        Schema::table('nfimpostos', function (Blueprint $table) {
            $table->boolean('piscofinsgeracredito')->default(0);
            $table->decimal('reducaoicms',10,2)->nullable();
            $table->decimal('pfreducaoicms',10,2)->nullable();
            $table->decimal('pftaxafecop',10,2)->nullable();
            $table->decimal('nfpisaliqcred',10,2)->nullable();
            $table->decimal('nfcofinsaliqcred',10,2)->nullable();
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
