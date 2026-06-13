<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNfimpostosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nfimpostos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('nfoperacao_id');
            $table->unsignedInteger('nfgrupofiscal_id');

            $table->unsignedInteger('nfcofins_id');
            $table->decimal('nfcofinsaliq', 10, 2);
            $table->decimal('nfcofinsaliqcred', 10, 2);
            $table->decimal('nfcofinsbase', 10, 2);

            $table->unsignedInteger('pfnfcofins_id');
            $table->decimal('pfnfcofinsaliq', 10, 2);
            $table->decimal('pfnfcofinsbase', 10, 2);

            $table->unsignedInteger('nficms_id');
            $table->decimal('nficmsaliq', 10, 2);
            $table->decimal('nficmsbase', 10, 2);

            $table->unsignedInteger('pfnficms_id');
            $table->decimal('pfnficmsaliq', 10, 2);
            $table->decimal('pfnficmsbase', 10, 2);

            $table->unsignedInteger('nfpis_id');
            $table->decimal('nfpisaliq', 10, 2);
            $table->decimal('nfpisaliqcred', 10, 2);
            $table->decimal('nfpisbase', 10, 2);

            $table->unsignedInteger('pfnfpis_id');
            $table->decimal('pfnfpisaliq', 10, 2);
            $table->decimal('pfnfpisaliqcred', 10, 2);
            $table->decimal('pfnfpisbase', 10, 2);

            $table->unsignedInteger('modalidadebcicms');
            $table->unsignedInteger('modalidadebcicmsst');
            $table->unsignedInteger('origemicms');
            $table->decimal('reducaoicms', 10, 4);
            $table->string('informacoesadicionalfisco', 250);
            $table->string('informacoesadicional', 250);

            $table->unsignedInteger('pfmodalidadebcicms');
            $table->unsignedInteger('pfmodalidadebcicmsst');
            $table->unsignedInteger('pforigemicms');
            $table->decimal('pfreducaoicms', 10, 4);
            $table->string('pfinformacoesadicionalfisco', 250);
            $table->string('pfinformacoesadicional', 250);

            $table->boolean('piscofinsgeracredito')->default(false);
            $table->string('piscofinstipocredito', 3)->default('');//Registro 1100
            $table->string('piscofinsnatreceita', 3)->default('');//Registro M410
            $table->string('piscofinstipobccredito', 3)->default('');//Registro M105

            $table->decimal('mva', 10, 2);
            $table->decimal('pfmva', 10, 2);
            $table->decimal('mvareduzido', 10, 2);
            $table->decimal('aliqicmsst', 10, 2);
            $table->decimal('pfaliqicmsst', 10, 2);
            $table->decimal('pftaxafecop', 10, 2);

            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('nfoperacao_id', 'nfimp_nfope_foreign')->references('id')->on('nfoperacaos');
            $table->foreign('nfgrupofiscal_id','nfimp_nfgrupofis_foreign')->references('id')->on('nfgrupofiscals');
            $table->foreign('nfcofins_id')->references('id')->on('nfcofins');
            $table->foreign('pfnfcofins_id')->references('id')->on('nfcofins');
            $table->foreign('nficms_id')->references('id')->on('nficms');
            $table->foreign('pfnficms_id')->references('id')->on('nficms');
            $table->foreign('nfpis_id')->references('id')->on('nfpis');
            $table->foreign('pfnfpis_id')->references('id')->on('nfpis');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('nfimpostos');
    }
}
