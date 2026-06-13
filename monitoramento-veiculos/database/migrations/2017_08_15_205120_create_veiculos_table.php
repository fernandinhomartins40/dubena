<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVeiculosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('veiculos', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('veiculotipo_id');
          $table->string('placa')->nullable()->default(null);
          $table->string('descricao')->nullable()->default(null);
          $table->decimal('km_atual', 15, 4)->nullable()->default(null);
          $table->string('unique_id')->nullable()->default(null);
          $table->boolean('ativo')->default(true);
          $table->timestamps();
          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id')->references('id')->on('empresas');
          $table->foreign('veiculotipo_id')->references('id')->on('veiculotipos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('veiculos');
    }
}
