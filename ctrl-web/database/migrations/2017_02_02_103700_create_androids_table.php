<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAndroidsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('androids', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('grupo_id');
          $table->unsignedInteger('empresa_id');
          $table->unsignedInteger('setor_id')->nullable()->default(null);
          $table->unsignedInteger('user_id')->nullable()->default(null);
          $table->string('descricao', 100);
          $table->string('serie', 30);
          $table->string('androidid', 30);
          $table->string('urlservidor', 200);
          $table->text('registrationid');
          $table->boolean('ativo')->default(true);

          $table->timestamps();

          $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
          $table->foreign('empresa_id')->references('id')->on('empresas');
          $table->foreign('setor_id')->references('id')->on('setors');
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
        Schema::drop('androids');
    }
}
