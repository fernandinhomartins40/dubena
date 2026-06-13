<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVehiclespositionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vehiclespositions', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();

            $table->integer("index");
            $table->integer("pedido_id");
            $table->double("latitude", 18, 15);
            $table->double("longitude", 18, 15);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vehiclespositions');
    }
}
