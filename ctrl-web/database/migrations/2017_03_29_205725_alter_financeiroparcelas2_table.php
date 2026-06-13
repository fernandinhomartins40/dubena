<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterFinanceiroparcelas2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('financeiroparcelas', function (Blueprint $table) {
            $table->dropColumn('datahorapagamento');
        });
        Schema::table('financeiroparcelas', function (Blueprint $table) {
            $table->dateTime('datahorabaixa')->nullable()->default(null);

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
