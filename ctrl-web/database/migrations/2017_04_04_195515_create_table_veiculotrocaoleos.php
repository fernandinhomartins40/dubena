<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableVeiculotrocaoleos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
     public function up()
    {
        Schema::create('veiculotrocaoleos', function(Blueprint $table){
            $table->increments('id');
            $table->unsignedInteger('id_veiculo');
            $table->unsignedInteger('id_colaborador');
            $table->date('data');
            $table->decimal('kmultimatrocaoleo',13,3);
            $table->decimal('kmtrocaoleo',13,3);
            $table->decimal('oleorendimento',13,3);
            $table->decimal('oleoproximatroca',13,3);
            $table->decimal('kmalertaantes',13,3);
            $table->boolean('alertaantes');
            $table->timestamps();
            
            $table->foreign('id_veiculo')->references('id')->on('veiculos')
                    ->onUpdate('cascade');
            $table->foreign('id_colaborador')->references('id')->on('colaboradors')
                    ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('veiculooleos');
    }
}
