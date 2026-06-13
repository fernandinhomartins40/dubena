<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableNfimpostoestados2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfimpostoestados', function (Blueprint $table) {
            $table->dropColumn('imposto_id');
        });
        Schema::table('nfimpostoestados', function (Blueprint $table) {
            $table->unsignedInteger('nfimposto_id');
            
            $table->foreign('nfimposto_id')->references('id')->on('nfimpostos')->onUpdate('cascade');
        });
        
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfimpostoestados', function (Blueprint $table) {
            //
        });
    }
}
