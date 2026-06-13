<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfimposto6Table extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfimpostos',
                function (Blueprint $table) {
            $table->dropColumn('modalidadebcicms');
            $table->dropColumn('modalidadebcicmsst');
            $table->dropColumn('pfmodalidadebcicms');
            $table->dropColumn('pfmodalidadebcicmsst');
            
            $table->dropColumn('reducaoicms');
            $table->dropColumn('pfreducaoicms');
        });

        Schema::table('nfimpostos',
                function (Blueprint $table) {
            $table->unsignedInteger('nfmotdesonicms')->nullable()->default(null);
            $table->unsignedInteger('pfnfmotdesonicms')->nullable()->default(null);
            $table->decimal('nficmsbasest', 10, 4)->nullable()->default(null);

            $table->unsignedInteger('modalidadebcicms')->nullable()->default(null);
            $table->unsignedInteger('modalidadebcicmsst')->nullable()->default(null);
            $table->unsignedInteger('pfmodalidadebcicms')->nullable()->default(null);
            $table->unsignedInteger('pfmodalidadebcicmsst')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfimpostos',
                function (Blueprint $table) {
            $table->dropColumn('pfnfmotdesonicms');
            $table->dropColumn('nfmotdesonicms');
            $table->dropColumn('nficmsbasest');

            $table->dropColumn('modalidadebcicms');
            $table->dropColumn('modalidadebcicmsst');
            $table->dropColumn('pfmodalidadebcicms');
            $table->dropColumn('pfmodalidadebcicmsst');
        });

        Schema::table('nfimpostos',
                function (Blueprint $table) {
            $table->unsignedInteger('modalidadebcicms')->nullable()->default(null);
            $table->unsignedInteger('modalidadebcicmsst')->nullable()->default(null);
            $table->unsignedInteger('pfmodalidadebcicms')->nullable()->default(null);
            $table->unsignedInteger('pfmodalidadebcicmsst')->nullable()->default(null);
            
            $table->decimal('reducaoicms', 10, 4)->nullable()->default(null);
            $table->decimal('pfreducaoicms', 10, 4)->nullable()->default(null);
        });
    }

}
