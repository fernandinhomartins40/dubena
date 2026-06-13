<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEmpresaUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::create('empresa_user', function (Blueprint $table) {

          $table->unsignedInteger('empresa_id')->index();
          $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade');
          $table->unsignedInteger('user_id')->index();
          $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
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
        Schema::drop('empresa_user');
    }
}
