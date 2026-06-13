<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateContausersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contausers', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('conta_id');
          $table->unsignedInteger('user_id');

          $table->boolean('operar')->default(false);
          $table->boolean('visualizar')->default(false);
          $table->boolean('transferir')->default(false);

          $table->timestamps();

          $table->foreign('conta_id')->references('id')->on('contas');
          $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('contausers');
    }
}
