<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableNfoperacaos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfoperacaos', function (Blueprint $table) {
            $table->dropColumn(['origem_icms', 'modalidadebcicms', 'modalidadebcicmsst']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfoperacaos', function (Blueprint $table) {
            //
        });
    }
}
