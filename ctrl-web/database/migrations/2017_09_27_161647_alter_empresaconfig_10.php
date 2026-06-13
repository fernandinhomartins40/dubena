<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterEmpresaconfig10 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('empresaconfigs', function (Blueprint $table) {
            $table->integer('presencacomprador')->nullable();
            $table->integer('fretemodalidade')->nullable();
            $table->unsignedInteger('ccfrete_id')->nullable();
            $table->unsignedInteger('pcfrete_id')->nullable();

            $table->foreign('ccfrete_id')->references('id')->on('centrocustos')->onUpdate('cascade');
            $table->foreign('pcfrete_id')->references('id')->on('planocontas')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('empresaconfigs', function (Blueprint $table) {
            
        });
    }
}
