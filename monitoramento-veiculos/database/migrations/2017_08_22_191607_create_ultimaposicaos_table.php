<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUltimaposicaosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ultimaposicaos', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('veiculo_id');
          $table->dateTime('datahora')->nullable()->default(null);
          $table->float('latitude', 23,15)->nullable()->default(null);
          $table->float('longitude', 23,15)->nullable()->default(null);
          $table->float('altitude', 23,15)->nullable()->default(null);
          $table->float('azimute', 23,15)->nullable()->default(null);
          $table->float('velocidade', 23,15)->nullable()->default(null);
          $table->float('velocidade_anterior', 23,15)->nullable()->default(null);
          $table->float('distancia', 23,15)->nullable()->default(null);
          $table->timestamps();
          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id')->references('id')->on('empresas');
          $table->foreign('veiculo_id')->references('id')->on('veiculos');
        });
        Schema::table('ultimaposicaos', function (Blueprint $table) {
          $table->float('latitude', 23,15)->nullable()->default(null)->change();
          $table->float('longitude', 23,15)->nullable()->default(null)->change();
          $table->float('altitude', 23,15)->nullable()->default(null)->change();
          $table->float('azimute', 23,15)->nullable()->default(null)->change();
          $table->float('velocidade', 23,15)->nullable()->default(null)->change();
          $table->float('velocidade_anterior', 23,15)->nullable()->default(null)->change();
          $table->float('distancia', 23,15)->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ultimaposicaos');
    }
}
