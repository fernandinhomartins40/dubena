<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AltertableVeiculopneus1 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('veiculopneus', function (Blueprint $table) {
            $table->dropColumn('alertaantes');
            $table->dropColumn('kmalertaantes');
        });
        Schema::table('veiculopneus', function(Blueprint $table) {
            $table->decimal('kmalertaantes',13,3)->nullable();
            $table->boolean('alertaantes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('veiculopneus', function (Blueprint $table) {
            //
        });
    }
}
