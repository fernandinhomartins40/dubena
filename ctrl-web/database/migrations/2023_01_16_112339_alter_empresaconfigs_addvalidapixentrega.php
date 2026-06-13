<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterEmpresaconfigsAddvalidapixentrega extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('empresaconfigs', function (Blueprint $table) {
            $table->boolean("validapixentrega")->default(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('empresaconfigs', function (Blueprint $table) {
            $table->dropColumn("validapixentrega");
        });
    }
}
