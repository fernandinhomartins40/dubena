<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F13 — índices de performance. Com o RLS (F02) filtrando TODA query por empresa_id,
 * as tabelas quentes precisam de índice começando por empresa_id. Estas duas
 * ganharam empresa_id (F00.5/F02) mas ficaram sem índice; e a parcela é muito
 * consultada por (empresa_id, baixado, vencimento) na cobrança.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contamovimentos') && Schema::hasColumn('contamovimentos', 'empresa_id')) {
            Schema::table('contamovimentos', function (Blueprint $t) {
                if (! $this->temIndice('contamovimentos', 'contamovimentos_empresa_id_datahora_index')) {
                    $t->index(['empresa_id', 'datahora']);
                }
            });
        }

        if (Schema::hasTable('estoquehistorico') && Schema::hasColumn('estoquehistorico', 'empresa_id')) {
            Schema::table('estoquehistorico', function (Blueprint $t) {
                if (! $this->temIndice('estoquehistorico', 'estoquehistorico_empresa_id_setor_id_produto_id_index')) {
                    $t->index(['empresa_id', 'setor_id', 'produto_id']);
                }
            });
        }

        if (Schema::hasTable('financeiroparcelas')) {
            Schema::table('financeiroparcelas', function (Blueprint $t) {
                if (! $this->temIndice('financeiroparcelas', 'financeiroparcelas_empresa_id_baixado_vencimento_index')) {
                    $t->index(['empresa_id', 'baixado', 'vencimento']);
                }
            });
        }
    }

    public function down(): void
    {
        $this->dropSeExistir('contamovimentos', 'contamovimentos_empresa_id_datahora_index');
        $this->dropSeExistir('estoquehistorico', 'estoquehistorico_empresa_id_setor_id_produto_id_index');
        $this->dropSeExistir('financeiroparcelas', 'financeiroparcelas_empresa_id_baixado_vencimento_index');
    }

    private function dropSeExistir(string $tabela, string $nome): void
    {
        if (Schema::hasTable($tabela) && $this->temIndice($tabela, $nome)) {
            Schema::table($tabela, fn (Blueprint $t) => $t->dropIndex($nome));
        }
    }

    private function temIndice(string $tabela, string $nome): bool
    {
        foreach (Schema::getIndexes($tabela) as $idx) {
            if (($idx['name'] ?? '') === $nome) {
                return true;
            }
        }

        return false;
    }
};
