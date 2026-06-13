<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterConvenioFechamentos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // FASE 3: cast para numeric exige USING no Postgres (helper trata).
        \App\Helpers\MigrationHelper::toDecimal('conveniofechamentos', 'valor', 15, 4);
        \App\Helpers\MigrationHelper::toDecimal('conveniofechamentopedidos', 'pedidovalor', 15, 4);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Schema::table('conveniofechamentos', function (Blueprint $table) {
        //     //
        // });
    }
}
