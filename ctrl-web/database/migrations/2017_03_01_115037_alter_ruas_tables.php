<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterRuasTables extends Migration
{
    /**
    * Run the migrations.
    *
    * @return void
    */
    public function up()
    {
        Schema::table('colaboradors', function($table)
        {
            $table->unsignedInteger('rua_id')->nullable()->index();
            $table->dropColumn('endereco')->nullable()->index();
            $table->foreign('rua_id')->references('id')->on('ruas');
        });
        Schema::table('clientes', function($table)
        {
            $table->unsignedInteger('rua_id')->nullable()->index();
            $table->dropColumn('endereco')->nullable()->index();
            $table->foreign('rua_id')->references('id')->on('ruas');
        });
        Schema::table('agencias', function($table)
        {
            $table->unsignedInteger('rua_id')->nullable()->index();
            $table->dropColumn('endereco')->nullable()->index();
            $table->foreign('rua_id')->references('id')->on('ruas');
        });
        Schema::table('setors', function($table)
        {
            $table->unsignedInteger('rua_id')->nullable()->index();
            $table->dropColumn('endereco')->nullable()->index();
            $table->foreign('rua_id')->references('id')->on('ruas');
        });
        Schema::table('empresas', function($table)
        {
            $table->unsignedInteger('rua_id')->nullable()->index();
            $table->dropColumn('endereco')->nullable()->index();
            $table->foreign('rua_id')->references('id')->on('ruas');
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
