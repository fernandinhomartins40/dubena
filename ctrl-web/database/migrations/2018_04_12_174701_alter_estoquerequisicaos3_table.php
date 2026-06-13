<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterEstoquerequisicaos3Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('estoquerequisicaos', function (Blueprint $table) {
            $table->dropColumn("centrocusto_id");
            $table->dropColumn("planoconta_id");
        });

        Schema::table('estoquerequisicaos', function (Blueprint $table) {
            $table->unsignedInteger("centrocusto_id")->nullable();
            $table->unsignedInteger("planoconta_id")->nullable();

            $table->foreign('centrocusto_id')->references('id')->on('centrocustos')->onUpdate('cascade');
            $table->foreign('planoconta_id')->references('id')->on('planocontas')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $this->up();
    }
}
