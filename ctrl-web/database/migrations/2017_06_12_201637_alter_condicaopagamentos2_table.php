<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterCondicaopagamentos2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('condicaopagamentos', function (Blueprint $table) {
            $table->dropColumn('taxa');
        });
        Schema::table('condicaopagamentos', function (Blueprint $table) {
            $table->decimal('taxa', 10, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('condicaopagamentos', function (Blueprint $table) {
            //
        });
    }
}
