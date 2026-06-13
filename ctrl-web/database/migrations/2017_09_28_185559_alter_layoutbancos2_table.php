<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterLayoutbancos2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('layoutbancos', function (Blueprint $table) {
            $table->unsignedInteger('maximodiasbaixadevolucao');
            $table->unsignedInteger('minimodiasbaixadevolucao');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('layoutbancos', function (Blueprint $table) {
            //
        });
    }
}
