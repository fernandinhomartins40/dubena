<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateChequesituacaohistoricosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('chequesituacaohistoricos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('chequerecebido_id');
            $table->unsignedInteger('chequesituacao_id');
            $table->date('datahoraprocesso');
            $table->timestamps();

            $table->foreign('chequerecebido_id', 'chequesituacaohist_chk')->references('id')->on('chequerecebidos');
            $table->foreign('chequesituacao_id', 'chequesituacaohist_sit')->references('id')->on('chequesituacaos');
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
