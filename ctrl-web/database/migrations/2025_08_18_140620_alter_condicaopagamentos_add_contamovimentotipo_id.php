<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterCondicaopagamentosAddContamovimentotipoId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('condicaopagamentos', function (Blueprint $table) {
            $table->unsignedInteger('contamovimentotipo_id')->nullable();

            $table->foreign('contamovimentotipo_id')->references('id')->on('contamovimentotipos');
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
            $table->dropColumn('contamovimentotipo_id');
        });
    }
}
