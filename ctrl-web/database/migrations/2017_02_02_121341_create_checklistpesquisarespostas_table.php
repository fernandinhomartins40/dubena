<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateChecklistpesquisarespostasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('checklistpesquisarespostas', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('checklistpesquisa_id');
          $table->unsignedInteger('checklistpergunta_id');
          $table->unsignedInteger('checklistresposta_id');
          $table->string('resposta', 500);
          $table->timestamps();

          $table->foreign('checklistpesquisa_id', 'chklistpesqresp_pesq_foreign')->references('id')->on('checklistpesquisas');
          $table->foreign('checklistpergunta_id', 'chklistpesqresp_perg_foreign')->references('id')->on('checklistperguntas');
          $table->foreign('checklistresposta_id', 'chklistpesqresp_resp_foreign')->references('id')->on('checklistrespostas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('checklistpesquisarespostas');
    }
}
