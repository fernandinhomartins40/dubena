<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropSoftdelete extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        
        Schema::table('telefonetipos', function($table) {
            
            $table->dropSoftDeletes(); //0 é que não foi feita a exclusao lógica. E 1, foi feita
        });
        Schema::table('clientesituacaos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('turnos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('planocontas', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('centrocustos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('clientecontatosituacaos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('clientecontatotipos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('recessotipos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('tipopessoas', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('segmentos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('setors', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('motivonaovendas', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('cargos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('colaboradors', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('estadocivils', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('pedidomotivoatrasos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('agencias', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('bancos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('produtoclasses', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('unidademedidas', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('parentescos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('tipoexames', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('veiculotipos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('tipocombustivels', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('tipodocumentos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('veiculos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('veiculodocumentos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('produtos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('empresabems', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('nfcests', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('checklisttipos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('condicaopagamentos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('pedidooperacaos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('contatipos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('contas', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('contatalaos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('contamovimentotipos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('pedidosituacaos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('valegassituacaos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('chequesituacaos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('promocaos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('produtoleiimpostos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('vendaativaocorrenciatipos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('nfgrupofiscals', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('nfcofins', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('nficms', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('nfipis', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('nfpis', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('nfoperacaos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('nfimpostos', function($table) {
            
            $table->dropSoftDeletes();
        });
        Schema::table('nfsituacaos', function($table) {
            
            $table->dropSoftDeletes();
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
