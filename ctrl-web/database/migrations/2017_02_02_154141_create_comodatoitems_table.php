<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateComodatoitemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('comodatoitems', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('comodato_id');
          $table->unsignedInteger('produto_id');
          $table->decimal('quantidade', 15, 4);

          $table->timestamps();

          $table->foreign('comodato_id')->references('id')->on('comodatos')->onDelete('cascade');
          $table->foreign('produto_id')->references('id')->on('produtos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('comodatoitems');
    }
}
