<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterBoletohistoricos3Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('boletohistoricos', function (Blueprint $table) {
            $table->unsignedInteger('boletoremessa_id')->nullable();

            $table->foreign('boletoremessa_id')->references('id')->on('boletoremessas')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('boletohistoricos', function (Blueprint $table) {
            $table->dropColumn('boletoremessa_id');
        });
    }
}
