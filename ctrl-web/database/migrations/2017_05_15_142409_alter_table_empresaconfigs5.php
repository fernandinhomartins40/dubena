<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableEmpresaconfigs5 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('empresaconfigs', function (Blueprint $table) {
            $table->dropColumn('percentualencargos');
            $table->dropColumn('percentualprovisaodevedores');
            $table->dropColumn('percentualremuneracaocapital');
            $table->dropColumn('percentualdistribuicaoresul');
            $table->dropColumn('impressaoautomatica');
            $table->dropColumn('impressaoqtdviaspedido');
        });
        Schema::table('empresaconfigs', function (Blueprint $table) {
            $table->integer('tempoidenchamada')->nullable();
            $table->decimal('percentualencargos',10,2)->nullable();
            $table->decimal('percentualprovisaodevedores',10,2)->nullable();
            $table->decimal('percentualremuneracaocapital',10,2)->nullable();
            $table->decimal('percentualdistribuicaoresul',10,2)->nullable();
            $table->boolean('impressaoautomatica')->default(0);
            $table->integer('impressaoqtdviaspedido')->nullable();
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
