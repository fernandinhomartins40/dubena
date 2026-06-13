<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMcmmTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mcmms', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('grupo_id');
            $table->date('datainiciofiltro');
            $table->date('datafimfiltro');
            $table->date('datamovimento');
            $table->string('razao_social', 200);
            $table->string('distribuidora', 200);
            $table->string('registro_anp', 20);
            $table->boolean('depd')->default(false);
            $table->boolean('depr')->default(false);
            $table->boolean('prt')->default(false);
            $table->boolean('prr')->default(false);
            $table->boolean('prd')->default(false);
            $table->unsignedInteger('capacidadearmazenamento')->nullable()->default(null);
            $table->string('endereco', 200);
            $table->string('cidade', 200);
            $table->string('observacoes', 200)->nullable();
            $table->string('uf');
            $table->string('cnpj');
            $table->unsignedInteger('codigo_ibge');
            $table->string('responsavel', 100);
            $table->timestamps();

            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('mcmm', function (Blueprint $table) {
            //
        });
    }
}
