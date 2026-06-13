<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfimpostoestados5Table extends Migration
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
            $table->decimal('nfaliqdiferimento', 10, 2)->nullable();
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
            $table->dropColumn('nfaliqdiferimento');
        });
    }

}
