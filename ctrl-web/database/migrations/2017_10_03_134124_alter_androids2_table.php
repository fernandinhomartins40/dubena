<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterAndroids2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('androids', function (Blueprint $table) {
            $table->dropColumn('ativo');
        });
        Schema::table('androids', function (Blueprint $table) {
            $table->boolean('ativo')->nullable()->default(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('androids', function (Blueprint $table) {
            //
        });
    }
}
