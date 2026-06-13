<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {

            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('erpurl');
            $table->time("horaabertura");
            $table->time("horafechamento");
            $table->rememberToken();
            $table->unsignedInteger('erpempresa_id');
            $table->boolean('admin')->default(false);
            $table->boolean('ativo')->default(false);

            $table->string("caminhoimagem", 45)->default("no-image.png");

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
        Schema::dropIfExists('users');
    }
}
