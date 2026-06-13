<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableMenuusers1 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('menuusers', function (Blueprint $table) {
            $table->boolean('baixar')->default(0);
            $table->boolean('alerta')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('menuusers', function (Blueprint $table) {
            $table->dropColumn('baixar');
            $table->dropColumn('alerta');
        });
    }
}
