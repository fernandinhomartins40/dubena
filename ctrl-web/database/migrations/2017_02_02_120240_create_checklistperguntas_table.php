<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateChecklistperguntasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('checklistperguntas', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('checklist_id');
          $table->string('descricao', 500);
          $table->unsignedInteger('tipopergunta');//0=check, 1=int, 2=string, 3=datetime, 4=radio
          $table->timestamps();

          $table->foreign('checklist_id')->references('id')->on('checklists')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('checklistperguntas');
    }
}
