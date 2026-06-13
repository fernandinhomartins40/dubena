<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTablesAtivoDefault extends Migration
{
    /**
     * Lista de tabelas que têm a coluna `ativo` redefinida como boolean default false.
     * FASE 3: nomes em minúsculas (PostgreSQL é case-sensitive; o original usava
     * MAIÚSCULAS, que no Oracle dava no mesmo mas no Postgres não casa).
     */
    private $tabelas = [
        'agencias', 'androids', 'bancolayoutretornos', 'bancos', 'binas', 'cargos',
        'centrocustos', 'checklistforms', 'checklists', 'checklisttipos',
        'clientecontatosituacaos', 'clientecontatotipos', 'clienteconveniodependentes',
        'clientes', 'clientesituacaos', 'colaboradorcomissaos', 'colaboradorfamilias',
        'colaboradors', 'comodatos', 'condicaopagamentos', 'contafechamentos',
        'contamovimentos', 'contamovimentotipos', 'contas', 'contatipos', 'empresabems',
        'empresas', 'empresas_grupos', 'estadocivils', 'motivonaovendas', 'nfcests',
        'nfgrupofiscals', 'nfsituacaos', 'parentescos', 'pedidomotivoatrasos',
        'pedidooperacaos', 'pedidosituacaos', 'planocontas', 'posvendas', 'produtoclasses',
        'produtos', 'promocaos', 'recessotipos', 'ruas', 'segmentos', 'setors',
        'telefonetipos', 'tipocombustivels', 'tipodocumentos', 'tipoexames', 'tipopessoas',
        'turnos', 'unidademedidas', 'users', 'veiculos', 'veiculotipos',
        'vendaativaocorrenciatipos', 'vendaativas',
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach ($this->tabelas as $tabela) {
            if (! Schema::hasTable($tabela)) {
                continue;
            }
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropColumn('ativo');
            });
            Schema::table($tabela, function (Blueprint $table) {
                $table->boolean('ativo')->nullable()->default(false);
            });
        }
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
