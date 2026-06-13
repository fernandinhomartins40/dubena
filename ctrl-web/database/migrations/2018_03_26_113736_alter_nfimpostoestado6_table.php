<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfimpostoestado6Table extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::table('nfimpostoestados',
                function (Blueprint $table) {
            $table->dropColumn('nficmsmodalidadebc');
            $table->dropColumn('nficmsstmodalidadebc');
            $table->dropColumn('pfnficmsmodalidadebc');
            $table->dropColumn('pfnficmsstmodalidadebc');
//
            $table->dropColumn('nficmsreducao');
            $table->dropColumn('pfnficmsreducao');
        });

        Schema::table('nfimpostoestados',
                function (Blueprint $table) {
            $table->unsignedInteger('nfmotdesonicms')->nullable()->default(null);
            $table->unsignedInteger('pfnfmotdesonicms')->nullable()->default(null);
            $table->decimal('nficmsbasest', 10, 4)->nullable()->default(null);
//
            $table->unsignedInteger('nficmsmodalidadebc')->nullable()->default(null);
            $table->unsignedInteger('nficmsstmodalidadebc')->nullable()->default(null);
            $table->unsignedInteger('pfnficmsmodalidadebc')->nullable()->default(null);
            $table->unsignedInteger('pfnficmsstmodalidadebc')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfimpostoestados',
                function (Blueprint $table) {
            $table->dropColumn('nfmotdesonicms');
            $table->dropColumn('pfnfmotdesonicms');
            $table->dropColumn('nficmsbasest');

            $table->dropColumn('nficmsmodalidadebc');
            $table->dropColumn('nficmsstmodalidadebc');
            $table->dropColumn('pfnficmsmodalidadebc');
            $table->dropColumn('pfnficmsstmodalidadebc');
        });

        Schema::table('nfimpostoestados',
                function (Blueprint $table) {
            $table->unsignedInteger('nficmsmodalidadebc')->nullable()->default(null);
            $table->unsignedInteger('nficmsstmodalidadebc')->nullable()->default(null);
            $table->unsignedInteger('pfnficmsmodalidadebc')->nullable()->default(null);
            $table->unsignedInteger('pfnficmsstmodalidadebc')->nullable()->default(null);

            $table->decimal('nficmsreducao', 10, 4)->nullable()->default(null);
            $table->decimal('pfnficmsreducao', 10, 4)->nullable()->default(null);
        });
    }

}
