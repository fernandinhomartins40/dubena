<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVeiculoSetorTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('setor_veiculo', function (Blueprint $table) {
            $table->unsignedInteger('setor_id')->index();
            $table->unsignedInteger('veiculo_id')->index();
            $table->foreign('setor_id')->references('id')->on('setors');
            $table->foreign('veiculo_id')->references('id')->on('veiculos');
          });
      }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('setor_veiculo');
    }
}
