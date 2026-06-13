<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateChequerecebidofinanceirosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('chequerecebidofinanceiros', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('chequerecebido_id');
          $table->unsignedInteger('financeiro_id');
          $table->unsignedInteger('financeiroparcela_id');

          $table->unsignedInteger('numerocheque');

          $table->timestamps();

          $table->foreign('chequerecebido_id')->references('id')->on('chequerecebidos')->onDelete('cascade');
          $table->foreign('financeiro_id', 'cheqrec_financeiro_foreign')->references('id')->on('financeiros');
          $table->foreign('financeiroparcela_id', 'cheqrec_parcela_foreign')->references('id')->on('financeiroparcelas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('chequerecebidofinanceiros');
    }
}
