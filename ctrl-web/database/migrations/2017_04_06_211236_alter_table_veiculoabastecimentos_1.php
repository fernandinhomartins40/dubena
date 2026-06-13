<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableVeiculoabastecimentos1 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('veiculoabastecimentos',function(Blueprint $table){
            $table->dropColumn('totallitros');
        });
        Schema::table('veiculoabastecimentos',function(Blueprint $table){
            $table->decimal('totallitros',13,3);
        });        
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
