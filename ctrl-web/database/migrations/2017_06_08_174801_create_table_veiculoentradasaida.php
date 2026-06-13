<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableVeiculoentradasaida extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('veiculoentradasaidas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('veiculo_id');
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('grupo_id');
            $table->boolean('entrada')->default(false);
            $table->boolean('saida')->default(false);
            $table->string('observacao',750)->nullable();
            $table->decimal('km',13,3);
            $table->decimal('ultimokm',13,3);
            $table->decimal('kmrodado',13,3);
            $table->string('temporodado');
            $table->date('ultimadatahora');
            $table->date('datahora');
            $table->timestamps();

            $table->foreign('veiculo_id')->references('id')->on('veiculos')->onUpdate('cascade');
            $table->foreign('empresa_id')->references('id')->on('empresas')->onUpdate('cascade');
            $table->foreign('grupo_id')->references('id')->on('empresas_grupos')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('veiculoentradasaidas');
        $this->up();
    }
}
