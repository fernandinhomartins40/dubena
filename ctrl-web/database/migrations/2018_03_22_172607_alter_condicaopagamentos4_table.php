<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterCondicaopagamentos4Table extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('condicaopagamentos',
                function (Blueprint $table) {
            $table->dropColumn('nfc_tpag');
        });
        Schema::table('condicaopagamentos',
                function (Blueprint $table) {
            $table->string('nfc_tpag', 2)->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('condicaopagamentos',
                function (Blueprint $table) {
            //não implementado porque o campo só mudou a obrigatoriedade e não vi há possibilidade de add ele obrigatório sem truncar a table
        });
    }

}
