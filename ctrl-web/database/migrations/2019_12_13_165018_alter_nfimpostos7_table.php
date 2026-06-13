<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfimpostos7Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfimpostos', function (Blueprint $table) {
            $table->unsignedInteger('beneficiario_id')->nullable();
            $table->unsignedInteger('pfbeneficiario_id')->nullable();

            $table->foreign('beneficiario_id')->references('id')->on('beneficiarios')->onUpdate('cascade');
            $table->foreign('pfbeneficiario_id')->references('id')->on('beneficiarios')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfimpostos', function (Blueprint $table) {
            $table->dropColumn('beneficiario_id');
            $table->dropColumn('pfbeneficiario_id');
        });
    }
}
