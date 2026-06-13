<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterChequerecebidos2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('chequerecebidos', function (Blueprint $table) {
            $table->dropColumn('observacao');
            $table->dropColumn('datapagamento');
        });
        Schema::table('chequerecebidos', function (Blueprint $table) {
            $table->string('observacao', 500)->nullable();
            $table->string('agencia', 10)->nullable();
            $table->date('datapagamento')->nullable();
        });
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('chequerecebidos', function (Blueprint $table) {
            //
        });
    }
}
