<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterColaborador7Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('colaboradors', function (Blueprint $table) {
            $table->dropColumn('foto');
            $table->dropColumn('segmento_id');
            $table->dropColumn('complemento');
        });
        Schema::table('colaboradors', function (Blueprint $table) {
            $table->string('complemento')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
