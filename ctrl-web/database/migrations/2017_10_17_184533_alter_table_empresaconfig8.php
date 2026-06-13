<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableEmpresaconfig8 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('empresaconfigs', function (Blueprint $table) {
            $table->unsignedInteger('ccdespesasjuros_id')->nullable();
            $table->unsignedInteger('ccdespesasdescontos_id')->nullable();

            $table->unsignedInteger('ccreceitasjuros_id')->nullable();
            $table->unsignedInteger('ccreceitasdescontos_id')->nullable();

            $table->foreign('ccdespesasjuros_id')->references('id')->on('centrocustos')->onUpdate('cascade');
            $table->foreign('ccdespesasdescontos_id')->references('id')->on('centrocustos')->onUpdate('cascade');
            
            $table->foreign('ccreceitasjuros_id')->references('id')->on('centrocustos')->onUpdate('cascade');
            $table->foreign('ccreceitasdescontos_id')->references('id')->on('centrocustos')->onUpdate('cascade');
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
            //
        });
    }
}
