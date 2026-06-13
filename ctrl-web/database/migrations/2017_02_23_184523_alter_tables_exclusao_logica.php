<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTablesExclusaoLogica extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('telefonetipos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);//0 é que não foi feita a exclusao lógica. E 1, foi feita
        });
        Schema::table('clientesituacaos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('turnos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('planocontas', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('centrocustos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('clientecontatosituacaos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('clientecontatotipos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('recessotipos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('tipopessoas', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('segmentos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('setors', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('motivonaovendas', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('cargos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('colaboradors', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('estadocivils', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('pedidomotivoatrasos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('agencias', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('bancos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('produtoclasses', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('unidademedidas', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('parentescos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('tipoexames', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('veiculotipos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('tipocombustivels', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('tipodocumentos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('veiculos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('veiculodocumentos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('produtos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('empresabems', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('nfcests', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('checklisttipos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('condicaopagamentos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('pedidooperacaos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('contatipos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('contas', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('contatalaos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('contamovimentotipos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('pedidosituacaos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('valegassituacaos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('chequesituacaos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('promocaos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('produtoleiimpostos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('vendaativaocorrenciatipos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('nfgrupofiscals', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('nfcofins', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('nficms', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('nfipis', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('nfpis', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('nfoperacaos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('nfimpostos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
        });
        Schema::table('nfsituacaos', function($table)
        {
            $table->unsignedInteger('deletado')->default(0);
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
