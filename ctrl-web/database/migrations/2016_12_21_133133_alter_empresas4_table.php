<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterEmpresas4Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('empresas', function($table)
      {
        $table->string('cnae')->nullable()->default(null);
        $table->string('suframa')->nullable()->default(null);
        $table->unsignedInteger('codigoestabelecimento')->nullable()->default(null);
        $table->boolean('depd')->default(false);
        $table->boolean('depr')->default(false);
        $table->boolean('prt')->default(false);
        $table->boolean('prr')->default(false);
        $table->boolean('prd')->default(false);
        $table->unsignedInteger('capacidadearmazenamento')->nullable()->default(null);
        $table->boolean('contingenciaemissao')->default(false);
        $table->dateTime('contingenciadatahora')->nullable()->default(null);
        $table->string('contingenciajustificativa')->nullable()->default(null);
        $table->text('certificadodigital')->nullable()->default(null);
        $table->unsignedInteger('codigoibgepais')->nullable()->default(null);

        $table->boolean('nfeemite')->default(false);
        $table->unsignedInteger('nfemodelo')->nullable()->default(null);
        $table->unsignedInteger('nfeserie')->nullable()->default(null);
        $table->unsignedInteger('nfenumero')->nullable()->default(null);
        $table->unsignedInteger('nfenumerohomologacao')->nullable()->default(null);
        $table->unsignedInteger('nfecrt')->nullable()->default(null);
        $table->unsignedInteger('nfetipoimpressao')->nullable()->default(null);
        $table->decimal('nfecreditosimplesnacional', 13, 3)->nullable()->default(null);
        $table->unsignedInteger('nfetipoemissao')->nullable()->default(null);
        $table->unsignedInteger('nfetipoambiente')->nullable()->default(null);

        $table->boolean('nfceemite')->default(false);
        $table->unsignedInteger('nfcemodelo')->nullable()->default(null);
        $table->unsignedInteger('nfceserie')->nullable()->default(null);
        $table->unsignedInteger('nfcenumero')->nullable()->default(null);
        $table->unsignedInteger('nfcenumerohomologacao')->nullable()->default(null);
        $table->unsignedInteger('nfcecrt')->nullable()->default(null);
        $table->unsignedInteger('nfcetipoimpressao')->nullable()->default(null);
        $table->decimal('nfcecreditosimplesnacional', 13, 3)->nullable()->default(null);
        $table->decimal('nfcevalorlimite', 13, 3)->nullable()->default(null);
        $table->unsignedInteger('nfcetipoemissao')->nullable()->default(null);
        $table->unsignedInteger('nfcetipoambiente')->nullable()->default(null);

        $table->boolean('spedemite')->default(false);
        $table->text('spedperfil')->nullable()->default(null);
        $table->unsignedInteger('spedatividade')->nullable()->default(null);
        $table->text('spedregistro1010')->nullable()->default(null);
        $table->unsignedInteger('spedincidenciatributaria')->nullable()->default(null);
        $table->unsignedInteger('spedapropriacaocredito')->nullable()->default(null);
        $table->unsignedInteger('spedtipocontribuicao')->nullable()->default(null);
        $table->unsignedInteger('spedregimecumulativo')->nullable()->default(null);
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
