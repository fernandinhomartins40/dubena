<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNfrecebidaitemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nfrecebidaitems', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('nfrecebida_id');
            $table->unsignedInteger('nfimposto_id');
            $table->unsignedInteger('nfoperacao_id');
            $table->unsignedInteger('setor_id');

            $table->string('cprod', 60);
            $table->string('cean', 14);
            $table->string('xprod', 120);
            $table->string('ncm', 8);
            $table->unsignedInteger('cfop');
            $table->string('ucom', 6);
            $table->decimal('qcom', 15, 4);
            $table->decimal('vuncom', 15, 4);
            $table->decimal('vprod', 15, 4);
            $table->string('ceantrib', 14);
            $table->string('utrib', 6);
            $table->decimal('qtrib', 15, 4);
            $table->decimal('vuntrib', 15, 4);
            $table->unsignedInteger('indTot');
            $table->string('tagicms', 10);
            $table->unsignedInteger('orig');
            $table->unsignedInteger('cst');
            $table->unsignedInteger('modbc');
            $table->decimal('vbc', 15, 4);
            $table->decimal('picms', 5, 2);
            $table->decimal('vicms', 15, 4);
            $table->decimal('vbcstret', 15, 4);
            $table->decimal('vicmsstret', 15, 4);
            $table->string('tagpis', 10);
            $table->unsignedInteger('cstpis');
            $table->decimal('vbcpis', 15, 4);
            $table->decimal('ppis', 5, 2);
            $table->decimal('vpis', 15, 4);
            $table->string('tagcofins', 10);
            $table->unsignedInteger('cstconfins');
            $table->decimal('vbcconfins', 15, 4);
            $table->decimal('pcofins', 5, 2);
            $table->decimal('vcofins', 15, 4);
            $table->decimal('qestoque', 15, 4);
            $table->string('tagipi', 10);
            $table->unsignedInteger('cstipi');
            $table->decimal('vbcipi', 15, 4);
            $table->decimal('pipi', 5, 2);
            $table->decimal('vipi', 15, 4);
            $table->decimal('predbcicms', 15, 4);
            $table->decimal('vdesc', 15, 4);
            $table->decimal('vfrete', 15, 4);
            $table->decimal('comissao', 5, 2);
            $table->decimal('aliqnac', 15, 4);
            $table->decimal('aliqimp', 15, 4);
            $table->decimal('impostonac', 15, 4);
            $table->decimal('impostoimp', 15, 4);
            $table->string('codigolote', 100);
            $table->decimal('picmsst', 5, 2);
            $table->boolean('controlaestoque')->default(true);
            $table->string('cprodanp', 9);
            $table->decimal('qbcprod', 14, 4);
            $table->decimal('valiqprod', 14, 4);
            $table->decimal('vcide', 14, 4);
            $table->decimal('taxafecop', 14, 4);

            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('nfrecebida_id')->references('id')->on('nfrecebidas');
            $table->foreign('nfimposto_id')->references('id')->on('nfimpostos');
            $table->foreign('nfoperacao_id')->references('id')->on('nfoperacaos');
            $table->foreign('setor_id')->references('id')->on('setors');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('nfrecebidaitems');
    }
}
