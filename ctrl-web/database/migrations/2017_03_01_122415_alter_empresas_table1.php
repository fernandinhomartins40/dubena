<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterEmpresasTable1 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        
        Schema::table('empresas', function($table)
        {
            $table->unsignedInteger('contrua_id')->nullable()->index();
            $table->dropColumn('contendereco')->nullable()->index();
            $table->foreign('contrua_id')->references('id')->on('ruas');
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
