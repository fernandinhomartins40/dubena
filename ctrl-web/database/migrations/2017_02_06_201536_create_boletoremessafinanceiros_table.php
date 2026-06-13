<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBoletoremessafinanceirosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('boletoremessafinanceiros', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('boletoremessa_id');
            $table->unsignedInteger('financeiro_id');
            $table->unsignedInteger('financeiroparcela_id');
            $table->unsignedInteger('conta_id');

            $table->dateTime('datahora');
            $table->unsignedInteger('numerosequencia');
            $table->string('nossonumero', 50);

            $table->boolean('cancelado')->default(false);
            $table->boolean('gerouremessa')->default(false);
            $table->boolean('protestar')->default(false);
            $table->unsignedInteger('protestardias');

            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('boletoremessa_id', 'bolremfin_bolrem_foreign')->references('id')->on('boletoremessas');
            $table->foreign('financeiro_id')->references('id')->on('financeiros');
            $table->foreign('financeiroparcela_id', 'bolremfin_finparc_foreign')->references('id')->on('financeiroparcelas');
            $table->foreign('conta_id')->references('id')->on('contas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('boletoremessafinanceiros');
    }
}
