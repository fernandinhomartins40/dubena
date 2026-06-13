<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterAppnfweb2Tables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('condicaopagamentos', function (Blueprint $table) {
            $table->unsignedInteger('pedidosituacaoappnf_id')->nullable()->default(null);
            $table->foreign('pedidosituacaoappnf_id')->references('id')->on('pedidosituacaos');
        });
        Schema::table('veiculos', function (Blueprint $table) {
            $table->string('placauf', 2)->nullable()->default(null);
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
