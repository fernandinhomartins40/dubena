<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateChequerecebidofinanceiroencontrocontasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('chequerecebidoencontrocontas', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('chequerecebido_id');
          $table->unsignedInteger('financeiroparcela_id');
          $table->decimal('valortotal', 8, 2);

          $table->timestamps();

          $table->foreign('chequerecebido_id', 'chqrec_encconta_fk')->references('id')->on('chequerecebidos')->onDelete('cascade');
          $table->foreign('financeiroparcela_id', 'chqrec_encconta_parc_fk')->references('id')->on('financeiroparcelas');
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
