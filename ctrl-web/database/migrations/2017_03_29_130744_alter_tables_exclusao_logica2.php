<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTablesExclusaoLogica2 extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('telefonetipos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes(); //0 é que não foi feita a exclusao lógica. E 1, foi feita
        });
        Schema::table('clientesituacaos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('turnos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('planocontas', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('centrocustos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('clientecontatosituacaos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('clientecontatotipos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('recessotipos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('tipopessoas', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('segmentos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('setors', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('motivonaovendas', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('cargos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('colaboradors', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('estadocivils', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('pedidomotivoatrasos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('agencias', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('bancos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('produtoclasses', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('unidademedidas', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('parentescos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('tipoexames', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('veiculotipos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('tipocombustivels', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('tipodocumentos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('veiculos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('veiculodocumentos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('produtos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('empresabems', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('nfcests', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('checklisttipos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('condicaopagamentos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('pedidooperacaos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('contatipos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('contas', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('contatalaos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('contamovimentotipos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('pedidosituacaos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('valegassituacaos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('chequesituacaos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('promocaos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('produtoleiimpostos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('vendaativaocorrenciatipos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('nfgrupofiscals', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('nfcofins', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('nficms', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('nfipis', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('nfpis', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('nfoperacaos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('nfimpostos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
        });
        Schema::table('nfsituacaos', function($table) {
            $table->dropColumn('deletado');
            $table->softDeletes();
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
