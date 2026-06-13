<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterComodatoTable2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('comodatos', function (Blueprint $table) {
            $table->dropColumn('numeronota');
        });
        Schema::table('comodatos', function (Blueprint $table) {
            $table->string('numeronota')->nullable()->default(null);
        });
        Schema::table('comodatos', function (Blueprint $table) {
            $table->dropColumn('observacao');
        });
        Schema::table('comodatos', function (Blueprint $table) {
            $table->string('observacao')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('comodatos', function (Blueprint $table) {
            //
        });
    }
}
