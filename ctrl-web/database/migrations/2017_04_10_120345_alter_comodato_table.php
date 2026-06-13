<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterComodatoTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('comodatos', function (Blueprint $table) {
            $table->dropColumn('entradasaida');
        });
        Schema::table('comodatos', function (Blueprint $table) {
            $table->tinyInteger('tipo');//0 Revenda p/ PJ - 1 Revenda p/ PF - 2 Distribuidora p/ Revenda
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
