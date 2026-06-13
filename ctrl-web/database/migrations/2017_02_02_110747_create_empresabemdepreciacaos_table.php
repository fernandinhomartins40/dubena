<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEmpresabemdepreciacaosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('empresabemdepreciacaos', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('empresabem_id');
          $table->date('depreciacaodata')->nullable()->default(null);
          $table->decimal('depreciacaovalor', 15, 4);

          $table->timestamps();

          $table->foreign('empresabem_id')->references('id')->on('empresabems')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('empresabemdepreciacaos');
    }
}
