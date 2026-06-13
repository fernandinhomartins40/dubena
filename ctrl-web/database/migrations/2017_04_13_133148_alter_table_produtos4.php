<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * FASE 3: reescrita para PostgreSQL. O original consultava USER_TAB_COLUMNS
 * (dicionário de dados do Oracle) para implementar "drop se existe / cria se
 * não existe". Substituído por Schema::hasColumn() — portável e idempotente,
 * preservando exatamente o mesmo efeito (recriar estes campos fiscais).
 */
class AlterTableProdutos4 extends Migration
{
    /** Campos a (re)criar e seus tipos. */
    private function campos()
    {
        return [
            ['nfecprodanp',          'string',           [9]],
            ['nfeqbcprod',           'decimal',          [15, 4]],
            ['nfevaliqprod',         'decimal',          [15, 4]],
            ['nfevcide',             'decimal',          [15, 4]],
            ['produtoretornavel_id', 'unsignedInteger',  []],
            ['nfgrupofiscal_id',     'unsignedInteger',  []],
            ['nfcest_id',            'unsignedInteger',  []],
            ['nfipi_id',             'unsignedInteger',  []],
        ];
    }

    public function up()
    {
        // Remove os que já existirem (idempotência do original).
        Schema::table('produtos', function (Blueprint $table) {
            foreach ($this->campos() as $c) {
                if (Schema::hasColumn('produtos', $c[0])) {
                    $table->dropColumn($c[0]);
                }
            }
        });

        // Recria todos como nullable.
        Schema::table('produtos', function (Blueprint $table) {
            foreach ($this->campos() as $c) {
                list($nome, $tipo, $args) = $c;
                if (Schema::hasColumn('produtos', $nome)) {
                    continue;
                }
                $params = array_merge([$nome], $args);
                call_user_func_array([$table, $tipo], $params)->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('produtos', function (Blueprint $table) {
            foreach ($this->campos() as $c) {
                if (Schema::hasColumn('produtos', $c[0])) {
                    $table->dropColumn($c[0]);
                }
            }
        });
    }
}
