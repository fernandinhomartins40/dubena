<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBoletohistoricosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('boletohistoricos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('grupo_id');
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('boleto_id');
            $table->unsignedInteger('financeiro_id');
            $table->unsignedInteger('financeiroparcela_id');
            $table->unsignedInteger('conta_id');

            $table->dateTime('datahora');
            $table->unsignedInteger('numerosequencia');
            $table->string('dv', 2);
            $table->string('nossonumero', 50);

            $table->boolean('cancelado')->default(false);
            $table->boolean('gerouboleto')->default(false);
            $table->boolean('gerouremessa')->default(false);

            $table->boolean('alterouvencimento')->default(false);
            $table->boolean('alteroucancelou')->default(false);

            $table->dateTime('alteracaodatahora');
            $table->unsignedInteger('alteracaouser_id');

            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('empresas_grupos');
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('financeiro_id')->references('id')->on('financeiros');
            $table->foreign('financeiroparcela_id', 'boletohist_finpar_foreign')->references('id')->on('financeiroparcelas');
            $table->foreign('conta_id')->references('id')->on('contas');
            $table->foreign('alteracaouser_id', 'boletohist_users_foreign')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('boletohistoricos');
    }
}
