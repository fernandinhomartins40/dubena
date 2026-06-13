<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrdersqueueTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ordersqueue', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();

            $table->integer("pedido_id");
            $table->integer("setor_id");
            $table->dateTime("updated");
            $table->integer("currentpositionindex")->default(0);
            $table->boolean("ended")->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ordersqueue');
    }
}
