<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCercapoligonosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cercapoligonos', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('cerca_id')->nullable()->default(null);
          $table->float('latitude', 23,15)->nullable()->default(null);
          $table->float('longitude', 23,15)->nullable()->default(null);
          
          $table->timestamps();
          $table->foreign('grupo_id')->references('id')->on('empresas_grupos')->onDelete('cascade')->onUpdate('cascade');
          $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade')->onUpdate('cascade');
          $table->foreign('cerca_id')->references('id')->on('cercas')->onDelete('cascade')->onUpdate('cascade');
        });
        Schema::table('cercapoligonos', function (Blueprint $table) {
            $table->float('latitude', 23,15)->nullable()->default(null)->change();
            $table->float('longitude', 23,15)->nullable()->default(null)->change();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cercapoligonos');
    }
}
