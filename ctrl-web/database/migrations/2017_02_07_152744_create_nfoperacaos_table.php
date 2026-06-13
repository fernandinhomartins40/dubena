<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNfoperacaosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nfoperacaos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');

            $table->string('descricao', 60);
            $table->string('descricaofiscal', 60);
            $table->unsignedInteger('cfop')->nullable()->default(null);
            $table->unsignedInteger('cfopie')->nullable()->default(null);
            $table->unsignedInteger('origem_icms')->nullable()->default(null);
            $table->string('informacoesadicionalfisco', 250)->nullable()->default(null);
            $table->unsignedInteger('modalidadebcicms')->nullable()->default(null);
            $table->unsignedInteger('modalidadebcicmsst')->nullable()->default(null);
            $table->string('movimentaestoque', 20);//NADA, ENTRADA, SAIDA
            $table->string('movimentafinanceiro', 20);//NADA, ENTRADA, SAIDA
            $table->unsignedInteger('aparecetela');//0-NFEntrada 1-NFSaida 2-Ambos
            $table->string('cadastronf', 1)->default('C');
            $table->boolean('spedvenda')->default(false);
            $table->boolean('deolhonoimposto')->default(false);

            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
            $table->foreign('empresa_id')->references('id')->on('empresas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('nfoperacaos');
    }
}
