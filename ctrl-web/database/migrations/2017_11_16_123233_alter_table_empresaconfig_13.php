<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableEmpresaconfig13 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('empresaconfigs', function (Blueprint $table) {
            $table->unsignedInteger('setor_ressarcimento')->nullable();
            $table->unsignedInteger('operacao_ressarcimento')->nullable();

            $table->foreign('setor_ressarcimento')->references('id')->on('setors')->onUpdate('cascade');
            $table->foreign('operacao_ressarcimento')->references('id')->on('nfoperacaos')->onUpdate('cascade');
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
            $table->dropColumn('setor_ressarcimento');
            $table->dropColumn('operacao_ressarcimento');
        });
    }
}
