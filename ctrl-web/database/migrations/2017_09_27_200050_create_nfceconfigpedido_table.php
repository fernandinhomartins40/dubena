<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNfceconfigpedidoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nfceconfigpedidos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('nfoperacao_id');
            $table->unsignedInteger('nfoperacaonova_id');
            $table->unsignedInteger('nfgrupofiscal_id');
            $table->timestamps();
            $table->foreign('nfoperacao_id')->references('id')->on('nfoperacaos');
            $table->foreign('nfoperacaonova_id')->references('id')->on('nfoperacaos');
            $table->foreign('nfgrupofiscal_id')->references('id')->on('nfgrupofiscals');
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
