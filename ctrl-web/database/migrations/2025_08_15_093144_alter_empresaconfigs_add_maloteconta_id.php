<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterEmpresaconfigsAddMalotecontaId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('empresaconfigs', function (Blueprint $table) {
            $table->unsignedInteger('maloteconta_id')->nullable();

            $table->foreign('maloteconta_id')->references('id')->on('contas')->onDelete('cascade');
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
            $table->dropColumn('maloteconta_id');
        });
    }
}
