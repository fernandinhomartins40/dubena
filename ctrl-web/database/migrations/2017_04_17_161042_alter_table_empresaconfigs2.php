<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableEmpresaconfigs2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('empresaconfigs', function (Blueprint $table) {
            $table->dropColumn('nfcecliente_id');
            $table->dropColumn('impressaotipo');
            $table->dropColumn('impressaomodelo');
            $table->dropColumn('impressaoporta');
            $table->dropColumn('pedidoemitenfce');            
        });
        
        Schema::table('empresaconfigs', function (Blueprint $table) {
            $table->unsignedInteger('nfcecliente_id')->nullable();
            $table->unsignedInteger('setorprincipal_id');
            $table->integer('impressaotipo')->nullable();//0-Comum 1-Bematech
            $table->string('impressaomodelo', 50)->nullable();
            $table->string('impressaoporta', 50)->nullable();
            $table->string('pedidoemitenfce',1)->default(0);            
            
            $table->foreign('nfcecliente_id')->references('id')->on('clientes')->onUpdate('cascade');
            $table->foreign('setorprincipal_id')->references('id')->on('setors')->onUpdate('cascade');
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
