<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterEstoquerequisicaoTable1 extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('estoquerequisicaos', function (Blueprint $table) {
            $table->unsignedInteger('cancelado')->default(0);
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
