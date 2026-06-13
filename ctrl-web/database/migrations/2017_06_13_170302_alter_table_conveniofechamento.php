<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableConveniofechamento extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('conveniofechamentos', function (Blueprint $table) {
            $table->dropColumn('financeiro_id');
        });
        Schema::table('conveniofechamentos', function (Blueprint $table) {
            $table->unsignedInteger('financeiro_id')->nullable();
            $table->unsignedInteger('condicaopagamento_id')->nullable();

            $table->foreign('condicaopagamento_id')->references('id')->on('condicaopagamentos')
                ->onUpdate('cascade');
            $table->foreign('financeiro_id')->references('id')->on('financeiros')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('conveniofechamentos', function (Blueprint $table) {
            //
        });
    }
}
