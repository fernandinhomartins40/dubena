<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableNfimpostos3 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfimpostos', function (Blueprint $table) {
            $table->unsignedInteger('piscofinstipocredito')->nullable();
            $table->unsignedInteger('piscofinsnatreceita')->nullable();
            $table->unsignedInteger('piscofinstipobccredito')->nullable();
            
            $table->foreign('piscofinstipocredito')->references('id')->on('creditopiscofins')->onUpdate('cascade');
            $table->foreign('piscofinsnatreceita')->references('id')->on('creditopiscofins')->onUpdate('cascade');
            $table->foreign('piscofinstipobccredito')->references('id')->on('creditopiscofins')->onUpdate('cascade');
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
