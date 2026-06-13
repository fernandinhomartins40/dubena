<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterAgenciaTable1 extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // FASE 3: Oracle é case-insensitive; Postgres não. A coluna foi criada
        // como 'postobeneficiario' (minúsculas) — referenciamos no mesmo caso.
        Schema::table('agencias', function($table) {
            $table->dropColumn('postobeneficiario');
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
