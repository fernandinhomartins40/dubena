<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBinasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('binas', function (Blueprint $table) {
          $table->increments('id');
          $table->string('descricao', 100);
          $table->unsignedInteger('baudrate');
          $table->unsignedInteger('databits');
          $table->boolean('discardnull');
          $table->boolean('dtrenable');
          $table->boolean('generatemember');
          $table->string('handshake', 50);
          $table->string('modifiers', 50);
          $table->string('parity', 50);
          $table->unsignedInteger('parityreplace');
          $table->string('portname', 50);
          $table->unsignedInteger('readbuffersize');
          $table->unsignedInteger('readtimeout');
          $table->unsignedInteger('receivedbytesthreshold');
          $table->boolean('rtsenable');
          $table->string('stopbits', 50);
          $table->unsignedInteger('writebuffersize');
          $table->unsignedInteger('writetimeout');
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
        Schema::drop('binas');
    }
}
