<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PF-3 (auditoria) — índice para o relatório DRE.
 *
 * O DRE (RelatorioService::dre) filtra `financeiroparcelas` por empresa_id +
 * `datahora_baixa` (parcelas efetivamente baixadas no período). Os índices
 * existentes cobrem (empresa_id, baixado, vencimento) — a COBRANÇA —, não a
 * apuração por data de baixa. Sob o dump real, o DRE varreria a tabela; este
 * índice composto o resolve.
 *
 * Idempotente. Portável (pgsql/sqlite).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('financeiroparcelas')
            && ! $this->temIndice('financeiroparcelas', 'financeiroparcelas_empresa_id_datahora_baixa_index')) {
            Schema::table('financeiroparcelas', function (Blueprint $t) {
                $t->index(['empresa_id', 'datahora_baixa']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('financeiroparcelas')
            && $this->temIndice('financeiroparcelas', 'financeiroparcelas_empresa_id_datahora_baixa_index')) {
            Schema::table('financeiroparcelas', function (Blueprint $t) {
                $t->dropIndex('financeiroparcelas_empresa_id_datahora_baixa_index');
            });
        }
    }

    private function temIndice(string $tabela, string $indice): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            return DB::selectOne('SELECT 1 AS x FROM pg_indexes WHERE tablename = ? AND indexname = ?', [$tabela, $indice]) !== null;
        }
        if ($driver === 'sqlite') {
            return DB::selectOne("SELECT 1 AS x FROM sqlite_master WHERE type='index' AND name = ?", [$indice]) !== null;
        }

        return false;
    }
};
