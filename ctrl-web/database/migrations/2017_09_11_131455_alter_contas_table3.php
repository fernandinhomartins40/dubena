<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterContasTable3 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contas', function (Blueprint $table) {
            $table->dropColumn('boletovencimentominimodias');
            $table->dropColumn('boletoposicoesnossonumero');
            $table->dropColumn('boletocnab');
            $table->dropColumn('boletovidesacadoravalista');
            $table->unsignedInteger('boletodiasprotesto')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contas', function (Blueprint $table) {
            //
        });
    }
}
