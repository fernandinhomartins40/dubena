<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableUsers4 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('alertaschecklist');
            $table->dropColumn('alertasfrota');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('support')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('alertaschecklist')->default(false);
            $table->boolean('alertasfrota')->default(false);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('support');
        });
    }
}
