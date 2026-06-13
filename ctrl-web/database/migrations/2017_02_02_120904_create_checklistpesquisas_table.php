<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateChecklistpesquisasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('checklistpesquisas', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('checklistform_id');
          $table->string('observacoes', 500);
          $table->dateTime('datahorapesquisa');
          $table->timestamps();

          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id')->references('id')->on('empresas');
          $table->foreign('checklistform_id')->references('id')->on('checklistforms');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('checklistpesquisas');
    }
}
