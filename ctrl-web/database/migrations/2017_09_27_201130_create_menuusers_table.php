<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMenuusersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('menuusers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('menu_id')->index();
            $table->unsignedInteger('user_id')->index();
            $table->boolean('visualizar')->default(0);
            $table->boolean('criar')->default(0);
            $table->boolean('editar')->default(0);
            $table->boolean('deletar')->default(0);
            $table->unsignedInteger('empresa_id')->nullable();
            $table->timestamps();
            
            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('menu_id')->references('id')->on('menus')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('menuusers');
    }
}
