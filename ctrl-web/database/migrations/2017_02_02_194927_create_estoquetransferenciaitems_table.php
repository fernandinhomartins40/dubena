<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEstoquetransferenciaitemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('estoquetransferenciaitems', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('estoquetransferencia_id');
          $table->unsignedInteger('produto_id');
          $table->decimal('quantidade', 15, 4);

          $table->timestamps();

          $table->foreign('estoquetransferencia_id')->references('id')->on('estoquetransferencias')->onDelete('cascade');
          $table->foreign('produto_id')->references('id')->on('produtos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('estoquetransferenciaitems');
    }
}
