<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVeiculotiposTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('veiculotipos', function (Blueprint $table) {
          $table->increments('id');
          $table->string('descricao');
          $table->string('imagem_parado')->nullable()->default(null);
          $table->string('imagem_movimento')->nullable()->default(null);
          $table->string('imagem_acima')->nullable()->default(null);
          $table->unsignedInteger('velocidade_maxima');
          $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('veiculotipos');
    }
}
