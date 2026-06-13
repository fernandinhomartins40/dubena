<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNfcestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nfcests', function (Blueprint $table) {
          $table->increments('id');
          $table->string('cest', 7);
          $table->string('ncm', 8);
          $table->string('descricao', 500);
          $table->boolean('ativo')->default(true);
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
        Schema::drop('nfcests');
    }
}
