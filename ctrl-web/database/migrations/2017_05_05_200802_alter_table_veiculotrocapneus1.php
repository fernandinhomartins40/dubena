<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableVeiculotrocapneus1 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('veiculopneus', function (Blueprint $table) {
            $table->unsignedInteger('empresa_id');
            
            $table->foreign('empresa_id')->references('id')->on('empresas')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('veiculopneus', function (Blueprint $table) {
            //
        });
    }
}
