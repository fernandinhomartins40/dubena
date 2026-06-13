<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableNfimpostoestados extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfimpostoestados', function (Blueprint $table) {
            $table->dropColumn('mva');
            $table->dropColumn('mvareduzido');
            $table->dropColumn('pfmva');
            
        });
        
        Schema::table('nfimpostoestados', function (Blueprint $table) {
            $table->decimal('mva',3,2)->nullable();
            $table->decimal('mvareduzido',3,2)->nullable();
            $table->decimal('pfmva',3,2)->nullable();
            
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
