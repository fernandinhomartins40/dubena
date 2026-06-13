<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateChequeemitidofinanceirosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('chequeemitidofinanceiros', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('chequeemitido_id');
          $table->unsignedInteger('financeiro_id');
          $table->unsignedInteger('financeiroparcela_id');

          $table->unsignedInteger('numerocheque');

          $table->timestamps();

          $table->foreign('chequeemitido_id')->references('id')->on('chequeemitidos')->onDelete('cascade');
          $table->foreign('financeiro_id', 'chequeemitfin_fin_foreign')->references('id')->on('financeiros');
          $table->foreign('financeiroparcela_id', 'chequeemitfin_parc_foreign')->references('id')->on('financeiroparcelas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('chequeemitidofinanceiros');
    }
}
