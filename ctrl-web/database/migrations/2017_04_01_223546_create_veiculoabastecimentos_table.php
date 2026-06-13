<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVeiculoabastecimentosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('veiculoabastecimentos', function(Blueprint $table){
            $table->increments('id');
            $table->unsignedInteger('veiculo_id');
            $table->unsignedInteger('colaborador_id');
            $table->unsignedInteger('empresa_id');
            $table->date('data');
            $table->decimal('mediaconsumo', 13,3);
            $table->decimal('kmatual', 13,3);
            $table->decimal('kmanterior', 13,3);
            $table->decimal('kmrodado', 13,3);
            $table->decimal('totallitros', 3,2);
            $table->timestamps();
            
            $table->foreign('veiculo_id')->references('id')->on('veiculos')
            ->onUpdate('cascade');
            $table->foreign('colaborador_id')->references('id')->on('colaboradors')
            ->onUpdate('cascade');
            $table->foreign('empresa_id')->references('id')->on('empresas')
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
        Schema::drop('veiculoabastecimentos');
    }
}
