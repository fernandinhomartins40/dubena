<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateChequerecebidotransferenciasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::create('chequerecebidotransferencias', function (Blueprint $table) {
          $table->increments('id');
            $table->unsignedInteger('contatransferencia_id');
            $table->unsignedInteger('chequerecebido_id');
            $table->string('tipotransferencia', 20);

            $table->timestamps();

            $table->foreign('chequerecebido_id', 'chequerec_chequerectransf_fk')->references('id')->on('chequerecebidos')->onDelete('cascade');
            $table->foreign('contatransferencia_id', 'contatransf_chequerectransf_fk')->references('id')->on('contatransferencias');
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
