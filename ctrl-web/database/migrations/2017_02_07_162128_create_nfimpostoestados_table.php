<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNfimpostoestadosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nfimpostoestados', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('imposto_id');
            $table->string('origem_uf', 2)->nullable()->default(null);
            $table->string('destino_uf', 2)->nullable()->default(null);
            
            $table->unsignedInteger('nficms_id');
            $table->decimal('nficmsaliq', 10, 2);
            $table->decimal('nficmsbase', 10, 2);
            $table->unsignedInteger('nficmsmodalidadebc');
            $table->unsignedInteger('nficmsstmodalidadebc');
            $table->unsignedInteger('nficmsorigem');
            $table->decimal('nficmsreducao', 10, 4);
            $table->decimal('nficmsstaliq', 10, 2);
            $table->decimal('mva', 10, 2);

            $table->unsignedInteger('pfnficms_id');
            $table->decimal('pfnficmsaliq', 10, 2);
            $table->decimal('pfnficmsbase', 10, 2);
            $table->unsignedInteger('pfnficmsmodalidadebc');
            $table->unsignedInteger('pfnficmsstmodalidadebc');
            $table->unsignedInteger('pfnficmsorigem');
            $table->decimal('pfnficmsreducao', 10, 4);
            $table->decimal('pfnficmsstaliq', 10, 2);
            $table->decimal('pfmva', 10, 2);

            $table->decimal('pftaxafecop', 10, 2);
            $table->decimal('mvareduzido', 10, 2);

            $table->timestamps();

            $table->foreign('grupo_id', 'nfimpest_emp_grupos_foreign')->references('id')->on('empresas_grupos');
            $table->foreign('empresa_id', 'nfimpest_empresas_foreign')->references('id')->on('empresas');
            $table->foreign('imposto_id', 'nfimpest_nfimpostos_foreign')->references('id')->on('nfimpostos');
            $table->foreign('origem_uf', 'orignfimpest_estados_foreign')->references('uf')->on('estados');
            $table->foreign('destino_uf', 'destnfimpest_estados_foreign')->references('uf')->on('estados');
            $table->foreign('nficms_id', 'nfimpest_nficms_foreign')->references('id')->on('nficms');
            $table->foreign('pfnficms_id', 'pfnfimpest_nficms_foreign')->references('id')->on('nficms');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('nfimpostoestados');
    }
}
