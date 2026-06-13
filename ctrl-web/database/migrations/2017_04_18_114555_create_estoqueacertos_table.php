<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEstoqueacertosTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('estoquesetoracertos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('produto_id');
            $table->unsignedInteger('setor_id');
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('quantidadeantiga');
            $table->unsignedInteger('quantidadenova');
            $table->unsignedInteger('user_id');
            $table->string('observacao');
            $table->dateTime('datahora');
            $table->timestamps();

            $table->foreign('produto_id')->references('id')->on('produtos')->onUpdate('cascade');
            $table->foreign('setor_id')->references('id')->on('setors')->onUpdate('cascade');
            $table->foreign('empresa_id')->references('id')->on('empresas')->onUpdate('cascade');
            $table->foreign('grupo_id')->references('id')->on('empresas_grupos')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }

}
