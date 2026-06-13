<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTablesComplementoIsnull extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('setors', function (Blueprint $table) {
            $table->dropColumn('complemento');
        });
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('complemento');
        });
        Schema::table('setors', function (Blueprint $table) {
            $table->string('complemento')->nullable()->default(null);
        });
        Schema::table('clientes', function (Blueprint $table) {
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
        Schema::table('setors', function (Blueprint $table) {
            //
        });
    }
}
