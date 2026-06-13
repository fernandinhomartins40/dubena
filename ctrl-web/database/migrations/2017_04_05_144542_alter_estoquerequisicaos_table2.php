<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterEstoquerequisicaosTable2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('estoquerequisicaos', function (Blueprint $table) {
            $table->dropColumn('observacoes');
        });
        Schema::table('estoquerequisicaos', function (Blueprint $table) {
            $table->string('observacoes')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('estoquerequisicaos', function (Blueprint $table) {
            //
        });
    }
}
