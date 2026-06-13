<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePixtransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pixtransactions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pedido_id');
            $table->string("txid", 100);
            $table->dateTime("expires_at");
            $table->unsignedBigInteger("loc_id");
            $table->string("loc_tipo");
            $table->dateTime("loc_criacao");
            $table->string("location")->nullable();
            $table->string("status");
            $table->decimal("valor", 10, 2);
            $table->string("correlation_id", 100);
            $table->string("pixcopiaecola")->nullable();
            $table->unsignedSmallInteger("revisao");
            $table->string("endtoendid", 100)->nullable();
            $table->decimal("valorpago", 10, 2)->nullable();
            $table->dateTime("datapagamento")->nullable();
            $table->timestamps();

            $table->foreign('pedido_id')->references('id')->on('pedidos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pixtransactions');
    }
}
