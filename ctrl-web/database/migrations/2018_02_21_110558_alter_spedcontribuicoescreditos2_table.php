<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterSpedcontribuicoescreditos2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('spedcontribuicoescreditos', function (Blueprint $table) {
            $table->dropColumn('reg');
            $table->unsignedInteger('registro')->nullable();
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
            $table->integer('reg')->nullable();
            $table->dropColumn('registro');
        });
    }
}
