<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateClientesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');
            $table->string('nome', 200);
            $table->date('datanascimento')->nullable()->default(null);
            $table->unsignedInteger('estadocivil_id')->nullable()->default(null);
            $table->string('rg', 20)->nullable()->default(null);
            $table->string('rgorgao', 20)->nullable();
            $table->string('rguf', 2)->nullable();
            $table->date('rgdataexpedicao')->nullable();
            $table->string('cpf', 20)->nullable();
            $table->string('sexo', 1)->nullable();

            $table->string('endereco', 500);
            $table->string('numero', 10);
            $table->string('complemento', 100);
            $table->string('email', 150)->nullable()->default(null);
            $table->unsignedInteger('cidade_id');
            $table->string('cep', 10)->nullable()->default(null);
            $table->unsignedInteger('bairro_id');

            $table->boolean('ativo');
            $table->timestamps();
            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('rguf')->references('uf')->on('estados');
            $table->foreign('bairro_id')->references('id')->on('bairros');
            $table->foreign('cidade_id')->references('id')->on('cidades');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('clientes');
    }
}
