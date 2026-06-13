<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterEstoquerequisicaoTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('estoquerequisicaos', function($table) {
            $table->unsignedInteger('planoconta_id');
            $table->unsignedInteger('centrocusto_id');
            $table->foreign('planoconta_id')->references('id')->on('planocontas');
            $table->foreign('centrocusto_id')->references('id')->on('centrocustos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }

}
