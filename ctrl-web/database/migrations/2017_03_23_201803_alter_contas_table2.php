<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterContasTable2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contas', function (Blueprint $table) {
            $table->unsignedInteger('banco_id')->nullable()->default(null);
            $table->string('agencia', 50)->nullable()->default(null);
            $table->boolean('boletoemite')->default(false);
            $table->unsignedInteger('boletosequencia')->nullable()->default(null);
            $table->string('boletocarteira', 3)->nullable()->default(null);
            $table->unsignedInteger('boletobyte')->nullable()->default(null);
            $table->decimal('boletomulta', 5, 3)->nullable()->default(null);
            $table->decimal('boletojuros', 5, 3)->nullable()->default(null);
            $table->string('boletoaceite', 5)->nullable()->default(null);
            $table->string('boletoespecie', 5)->nullable()->default(null);
            $table->unsignedInteger('boletoremessasequencia')->nullable()->default(null);
            $table->string('boletocedente', 50)->nullable()->default(null);
            $table->string('boletocedentedigito', 2)->nullable()->default(null);
            $table->boolean('boletocomprovanteentrega')->default(false);
            $table->string('boletoinstrucoes', 1000)->nullable()->default(null);
            $table->unsignedInteger('boletovencimentominimodias')->nullable()->default(null);
            $table->unsignedInteger('boletoposicoesnossonumero')->nullable()->default(null);
            $table->boolean('boletovidesacadoravalista')->default(false);
            $table->unsignedInteger('boletocnab')->nullable()->default(null);
            $table->boolean('boletocorrespondente')->default(false);
            $table->unsignedInteger('boletocorrespondentebanco_id')->nullable()->default(null);

            $table->boolean('fechado')->default(false);
            $table->boolean('ativo')->default(true);
            $table->foreign('boletocorrespondentebanco_id')->references('id')->on('bancos');
            $table->foreign('banco_id')->references('id')->on('bancos');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
