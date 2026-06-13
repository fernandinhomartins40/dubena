<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterRuas2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ruas', function (Blueprint $table) {
            $table->dropColumn('cep');
        });
        Schema::table('ruas', function (Blueprint $table) {
            $table->string('cep', 10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ruas', function (Blueprint $table) {
            //
        });
    }
}
