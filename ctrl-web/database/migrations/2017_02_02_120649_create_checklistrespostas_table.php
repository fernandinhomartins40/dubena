<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateChecklistrespostasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('checklistrespostas', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('checklistpergunta_id');
          $table->string('descricao', 500);
          $table->unsignedInteger('tipopergunta');//0=check, 1=int, 2=string, 3=datetime, 4=radio
          $table->string('resposta', 500);
          $table->boolean('alerta')->default(false);
          $table->timestamps();

          $table->foreign('checklistpergunta_id')->references('id')->on('checklistperguntas')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('checklistrespostas');
    }
}
