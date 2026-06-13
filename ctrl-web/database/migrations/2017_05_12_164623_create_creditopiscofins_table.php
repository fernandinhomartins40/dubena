<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCreditopiscofinsTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('creditopiscofins', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('identificador');
            $table->integer('codigo');
            $table->text('descricao',1000);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::drop('creditopiscofins');
    }

}
