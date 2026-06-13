<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterBoletohistoricos2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('boletohistoricos', function (Blueprint $table) {
            $table->dropColumn('financeiro_id');
            $table->dropColumn('financeiroparcela_id');
        });

        Schema::table('boletohistoricos', function (Blueprint $table) {
            $table->string('info_cancelamento')->nullable();
            $table->unsignedInteger('financeiro_id')->nullable();
            $table->unsignedInteger('financeiroparcela_id')->nullable();
            $table->unsignedInteger('ocorrencia_id')->nullable();
            $table->foreign('ocorrencia_id')->references('id')->on('ocorrenciasremessas');
            $table->foreign('financeiro_id')->references('id')->on('financeiros');
            $table->foreign('financeiroparcela_id', 'boletohist_finpar_foreign')->references('id')->on('financeiroparcelas');
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
            //
        });
    }
}
