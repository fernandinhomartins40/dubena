<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNfemitidaparcelasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nfemitidaparcelas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('nfemitida_id');
            
            $table->unsignedInteger('numeroparcela');
            $table->string('referencia', 7);
            $table->date('datavencimento');
            $table->decimal('valororiginal', 15, 4);
            $table->decimal('moradiaria', 15, 4);
            $table->decimal('valormulta', 15, 4);
            $table->decimal('valorjuros', 15, 4);
            $table->boolean('baixado')->default(false);
            
            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('nfemitida_id')->references('id')->on('nfemitidas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('nfemitidaparcelas');
    }
}
