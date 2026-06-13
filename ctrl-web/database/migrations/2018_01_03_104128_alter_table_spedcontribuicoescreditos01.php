<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableSpedcontribuicoescreditos01 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('spedcontribuicoescreditos', function (Blueprint $table) {
            $table->dropColumn('registro');
            $table->dropColumn('orig_cred');
        });

        Schema::table('spedcontribuicoescreditos', function (Blueprint $table) {
            $table->string('orig_cred',2)->nullable();
            $table->integer("reg")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('spedcontribuicoescreditos', function (Blueprint $table) {
            $table->dropColumn('orig_cred');
            $table->dropColumn('reg');
        });

        Schema::table('spedcontribuicoescreditos', function (Blueprint $table) {
            $table->integer("orig_cred")->nullable();
            $table->unsignedInteger("registro")->nullable()->default(null);
        });
    }
}
