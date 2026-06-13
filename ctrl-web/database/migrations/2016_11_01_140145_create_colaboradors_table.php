<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateColaboradorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('colaboradors', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');
            $table->string('nome', 100);
            $table->string('email', 100);
            $table->date('datanascimento')->nullable()->default(null);
            $table->date('dataadmissao')->nullable()->default(null);
            $table->date('datadesligamento')->nullable()->default(null);
            $table->string('sexo', 1)->nullable();
            $table->string('cpf', 20)->nullable();
            $table->string('rg', 20)->nullable()->default(null);
            $table->string('rgorgao', 20)->nullable();
            $table->string('rguf', 2)->nullable();
            $table->unsignedInteger('estadocivil_id')->nullable()->default(null);

            $table->string('endereco', 500);
            $table->string('numero', 10);
            $table->string('complemento', 100);
            $table->unsignedInteger('cidade_id');
            $table->string('cep', 10)->nullable()->default(null);
            $table->unsignedInteger('bairro_id');
            $table->unsignedInteger('setor_id');

            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('rguf')->references('uf')->on('estados');
            $table->foreign('bairro_id')->references('id')->on('bairros');
            $table->foreign('cidade_id')->references('id')->on('cidades');
            $table->foreign('setor_id', 'colaborador_setor_foreign')->references('id')->on('setors');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('colaboradors');
    }
}
