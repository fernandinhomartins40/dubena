<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterNfsituacaos2Table extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nfsituacaos',
                function (Blueprint $table) {
            $table->dropColumn('msgerrosistema');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nfsituacaos',
                function (Blueprint $table) {
            $table->string('msgerrosistema', 500)->nullable();
        });
    }

}
