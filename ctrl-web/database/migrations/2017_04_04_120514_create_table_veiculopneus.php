<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableVeiculopneus extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('veiculopneus', function(Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('veiculo_id');
            $table->date('data');
            $table->decimal('km', 13, 3);
            $table->decimal('vidautilkm', 13, 3);
            $table->decimal('valor', 13, 3);
            $table->integer('quantidade');
            $table->string('medidapneus');
            $table->boolean('alertaantes');
            $table->decimal('kmalertaantes', 13, 3);
            $table->timestamps();

            $table->foreign('veiculo_id')->references('id')->on('veiculos')
                    ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::drop('veiculopneus');
    }

}
