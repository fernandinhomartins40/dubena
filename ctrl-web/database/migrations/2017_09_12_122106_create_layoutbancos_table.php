<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLayoutbancosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('layoutbancos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cnab');
            $table->unsignedInteger('minimodiasprotesto');
            $table->unsignedInteger('maximodiasprotesto');
            $table->unsignedInteger('boletoposicoesnossonumero');
            $table->unsignedInteger('codigo_banco');
            $table->string('descricao', 50);
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
