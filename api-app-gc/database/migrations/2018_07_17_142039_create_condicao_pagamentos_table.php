<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCondicaoPagamentosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('condicaopagamentos', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->string("descricao", 45);
            $table->tinyInteger("tipo");
            //0 => dinhero, 1 => debito, 2 => crétido, 3 => valegas, 4 => convênio, 5 => cheque, 6 => online

            $table->string("caminhoimagem", 45)->default("no-image.png");

            $table->string("ativo")->default(false);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('condicaopagamentos');
    }
}
